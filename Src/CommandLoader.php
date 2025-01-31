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
	 * @param array<int,array<string,string>>     $namespacedDirectory
	 * @param array<string,class-string<Console>> $commands
	 */
	final private function __construct(
		private Container $container,
		private array $namespacedDirectory = array(),
		private array $commands = array(),
		private ?EventDispatcher $dispatcher = null
	) {
		$container->get( Cli::class )->eventDispatcher()->addSubscriber( new CommandSubscriber() );
	}

	public function getContainer(): Container {
		return $this->container;
	}

	/** @return array<int,array<string,string>> List of directory name indexed by its namespace. */
	public function getNamespacedDirectories(): array {
		return $this->namespacedDirectory;
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

	/** @param callable(EventTask): void $listener */
	public static function withEvent( callable $listener, ?Container $container = null ): static {
		return self::getInstance( $container, event: true )->withListener( $listener );
	}

	/** @param array<int,array<string,string>> $namespacedDirectories */
	public static function loadCommands( array $namespacedDirectories, ?Container $container = null ): static {
		return self::getInstance( $container, event: false )->inDirectory( $namespacedDirectories )->load();
	}

	/** @param array<int,array<string,string>> $namespacedDirectories The directory path and namespace map. */
	public function inDirectory( array $namespacedDirectories ): static {
		array_walk( $namespacedDirectories, $this->register( ... ) );

		return $this;
	}

	public function load(): static {
		return $this->startScan();
	}

	protected function getRootPath(): string {
		return $this->realDirectoryPath( $this->base['dirpath'] );
	}

	protected function forCurrentSubDirectory(): void {
		$this->scan( directory: $this->currentItem()->getPathname() );
	}

	protected function forCurrentFile(): void {
		if ( ! $commandClass = $this->fromFilePathToPsr4SpecificationFullyQualifiedClassName() ) {
			return;
		}

		if ( ! is_a( $commandClass, Console::class, allow_string: true ) ) {
			return;
		}

		$lazyload                       = array( $commandClass, 'start' );
		$commandName                    = $commandClass::asCommandName();
		$this->commands[ $commandName ] = $commandClass;

		$this->handleResolved( $commandClass, $lazyload, $commandName );

		// Allow developers to listen for resolved command by Command Loader with the "EventTask".
		if ( $commandRunner = $this->getCommandRunnerFromEvent() ) {
			$commandRunner( new EventTask( $lazyload( ... ), $commandClass, $commandName, $this->container ) );
		}
	}

	/**
	 * Allows developers to handle the resolved command.
	 *
	 * By default, it defers command instantiation until current command is ran.
	 *
	 * @param class-string<Console>     $classname
	 * @param callable(Container): void $command
	 * @link https://symfony.com/doc/current/console/lazy_commands.html
	 */
	protected function handleResolved( string $classname, callable $command, string $commandName ): void {
		$this->container->set( $classname, $command );
	}

	private static function getInstance( ?Container $container, bool $event = false ): static {
		$container ??= Container::boot();

		return new static( $container, dispatcher: $event ? new EventDispatcher() : null );
	}

	private function fromFilePathToPsr4SpecificationFullyQualifiedClassName(): ?string {
		return ( $fileSubpathParts = $this->currentItemSubpath( parts: true ) )
			? "{$this->base['namespace']}\\{$this->withoutExtension( implode( '\\', $fileSubpathParts ) )}"
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

	/** @param array<string,string> $namespacedDirectory */
	private function register( array $namespacedDirectory ): void {
		$this->namespacedDirectory[] = $namespacedDirectory;
	}

	private function startScan(): static {
		if ( $this->scanStarted ?? false ) {
			return $this;
		}

		$this->scanStarted = true;

		foreach ( $this->namespacedDirectory as $base ) {
			$dirpath    = $base[ $namespace = (string) array_key_first( $base ) ];
			$this->base = compact( 'dirpath', 'namespace' );

			$this->scan( $this->realDirectoryPath( $dirpath ) );
		}

		// By default, all lazy-loaded commands extending Console will use default "Cli".
		// Different application may be used with setter "Console::setApplication()".
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
