<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Countable;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\EventTask;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Cli\Event\AfterLoadEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TheWebSolver\Codegarage\Cli\Event\CommandSubscriber;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

class CommandLoader implements Countable {
	use DirectoryScanner {
		DirectoryScanner::usingSubDirectories as public;
	}

	/** @var array{dirpath:string,namespace:string} */
	protected array $base;
	private bool $scanStarted;

	/**
	 * @param Container                           $container
	 * @param array<array{0:string,1:string}>     $directoryPathAndNamespace
	 * @param array<string,class-string<Console>> $commands
	 */
	final private function __construct(
		private Container $container,
		private array $directoryPathAndNamespace,
		private array $commands = array(),
		private ?EventDispatcher $dispatcher = null
	) {
		$container->get( Cli::class )->eventDispatcher()->addSubscriber( new CommandSubscriber() );
	}

	public function getContainer(): Container {
		return $this->container;
	}

	/** @return array<array{0:string,1:string}> List of directory name and its command namespace. */
	public function getDirectoryNamespaceMap(): array {
		return $this->directoryPathAndNamespace;
	}

	/** @return array<string,class-string<Console>> */
	public function getCommands(): array {
		return $this->commands;
	}

	/**
	 * Registers sub-directories to be scanned when scanning the given directory and namespace map.
	 *
	 * Namespaces will be auto-generated appending those sub-directories for making them PSR-4 compliant.
	 *
	 * @param array<string,int|int[]> $nameWithDepth Sub-directory name and its depth (depths if same name
	 *                                               in nested directory path) to scan for command files.
	 */
	public static function withSubdirectories( array $nameWithDepth, ?Container $container = null ): static {
		return self::getInstance( $container )->usingSubDirectories( $nameWithDepth );
	}

	/** @param array{0:string,1:string}[] $directoryPathAndNamespaces */
	public static function load( array $directoryPathAndNamespaces, ?Container $container = null ): static {
		return self::getInstance( $container, $directoryPathAndNamespaces, event: false )->startScan();
	}

	/** @param callable(EventTask): void $listener */
	public static function subscribeWith( callable $listener, ?Container $container = null ): static {
		return self::getInstance( $container, event: true )->withListener( $listener );
	}

	/**
	 * @param array{0:string,1:string} $pathAndNamespace     The directory path and namespace map.
	 * @param array{0:string,1:string} ...$pathAndNamespaces Additional directory path and namespace map.
	 */
	public function inDirectory( array $pathAndNamespace, array ...$pathAndNamespaces ): static {
		$this->register( $pathAndNamespace );

		array_walk( $pathAndNamespaces, $this->register( ... ) );

		return $this;
	}

	public function loadCommands(): static {
		return $this->startScan();
	}

	/** @param array{0:string,1:string}[] $map */
	private static function getInstance( ?Container $c, array $map = array(), bool $event = false ): static {
		$c ??= Container::boot();

		return new static( $c, $map, dispatcher: $event ? new EventDispatcher() : null );
	}

	final protected function scanDirectory(): void {
		$this->scan( directory: $this->currentItem()->getPathname() );
	}

	protected function getRootPath(): string {
		return $this->realDirectoryPath( $this->base['dirpath'] );
	}

	protected function execute(): void {
		if ( ! $commandClass = $this->fromCurrentItemToPsr4SpecificationFullyQualifiedClassName() ) {
			return;
		}

		if ( ! is_a( $commandClass, Console::class, allow_string: true ) ) {
			return;
		}

		$lazyload                       = array( $commandClass, 'start' );
		$commandName                    = $commandClass::asCommandName();
		$this->commands[ $commandName ] = $commandClass;

		$this->handleResolved( $commandClass, $lazyload, $commandName );

		// Allow third-party to listen for resolved command by Command Loader  with the "EventTask".
		if ( $commandRunner = $this->getCommandRunnerFromEvent() ) {
			$commandRunner( new EventTask( $lazyload( ... ), $commandClass, $commandName, $this->container ) );
		}
	}

	/**
	 * Allows developers to handle the resolved commands.
	 *
	 * By default, it defers command instantiation until current command is ran.
	 *
	 * @param class-string<Console>     $classname
	 * @param callable(Container): void $command
	 * @link https://symfony.com/doc/current/console/lazy_commands.html
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	protected function handleResolved( string $classname, callable $command, string $commandName ): void {
		$this->container->set( $classname, $command );
	}

	private function fromCurrentItemToPsr4SpecificationFullyQualifiedClassName(): ?string {
		return ( $subNamespacedFileParts = $this->currentItemSubpath( parts: true ) )
			? $this->withoutExtension( "{$this->base['namespace']}\\" . implode( '\\', $subNamespacedFileParts ) )
			: null;
	}

	/** @param callable(EventTask): void $listener */
	private function withListener( callable $listener ): static {
		$this->dispatcher?->addListener(
			AfterLoadEvent::class,
			static fn( AfterLoadEvent $e ) => $e->runCommand( $listener( ... ) )
		);

		return $this;
	}

	/** @param array{0:string,1:string} $directoryAndNamespace */
	private function register( array $directoryAndNamespace ): void {
		$this->directoryPathAndNamespace[] = $directoryAndNamespace;
	}

	private function startScan(): static {
		if ( $this->scanStarted ?? false ) {
			return $this;
		}

		$this->scanStarted = true;

		foreach ( $this->directoryPathAndNamespace as [$dirpath, $namespace] ) {
			$this->base = compact( 'dirpath', 'namespace' );

			$this->scan( $this->realDirectoryPath( $dirpath ) );
		}

		// By default, all lazy-loaded commands extending Console will use default "Cli".
		// Different application maybe used with setter "Console::setApplication()".
		$this->container
			->get( Cli::class )
			->setCommandLoader( new ContainerCommandLoader( $this->container, $this->commands ) );

		return $this;
	}

	/** @return ?callable(EventTask): void */
	private function getCommandRunnerFromEvent(): ?callable {
		return $this->dispatcher?->dispatch( new AfterLoadEvent() )->getCommandRunner();
	}
}
