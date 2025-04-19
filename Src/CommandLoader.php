<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Countable;
use LogicException;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\EventTask;
use TheWebSolver\Codegarage\Cli\Event\AfterLoadEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TheWebSolver\Codegarage\Cli\Event\CommandSubscriber;
use TheWebSolver\Codegarage\Cli\Traits\DirectoryScanner;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

class CommandLoader implements Countable {
	use DirectoryScanner;

	/** @var array{dirpath:string,namespace:string} */
	protected array $base;
	private bool $scanStarted;
	private ContainerInterface $container;

	/**
	 * @param array<int,array<string,string>>     $namespacedDirectory
	 * @param array<string,class-string<Console>> $commands
	 */
	final private function __construct(
		private array $namespacedDirectory = [],
		private array $commands = [],
		private ?EventDispatcher $dispatcher = null
	) {}

	/** @return array<int,array<string,string>> List of directory path indexed by its namespace. */
	public function getNamespacedDirectories(): array {
		return $this->namespacedDirectory;
	}

	/** @return array<string,class-string<Console>> */
	public function getCommands(): array {
		return $this->commands;
	}

	public static function start(): static {
		return self::getInstance();
	}

	/** @param callable(EventTask): void $listener */
	public static function withEvent( callable $listener ): static {
		return self::getInstance( event: true )->withListener( $listener );
	}

	/** @param array<int,array<string,string>> $namespacedDirectories */
	public static function loadCommands( array $namespacedDirectories, ContainerInterface $container ): static {
		$loader = self::getInstance();

		$loader->namespacedDirectory = $namespacedDirectories;

		return $loader->load( $container );
	}

	public function inDirectory( string $path, string $namespace ): static {
		$this->namespacedDirectory[] = [ $namespace => $path ];

		return $this;
	}

	final public function load( ContainerInterface $container ): static {
		if ( $this->scanStarted ?? false ) {
			return $this;
		}

		$this->scanStarted = true;
		$this->container   = $container;

		$this->getApp()->eventDispatcher()->addSubscriber( new CommandSubscriber() );

		$this->initialize();

		return $this->startScan();
	}

	/**
	 * Allows inheriting class to initialize its actions before scan has started.
	 */
	protected function initialize(): void {}

	/**
	 * Returns an instance of Console application (Cli by default).
	 *
	 * This may throw PSR-11 exceptions if no container binding identifier exists with $id as `Cli::class`.
	 *
	 * @throws LogicException When container resolves something other than `Cli` or its inheritance.
	 */
	final protected function getApp(): Cli {
		return ( $app = $this->container->get( Cli::class ) ) instanceof Cli
			? $app
			: throw new LogicException( 'Impossible to start Cli application using container.' );
	}

	/**
	 * Allows inheriting class to handle the found command.
	 *
	 * By default, it defers command instantiation until current command is ran. The provided container
	 * must have `ContainerInterface::set()` method to defer command instantiation. If that is not the
	 * case, then this method must be overridden to handle found command (preferably defer loading).
	 *
	 * @param class-string<Console>              $classname
	 * @param callable(ContainerInterface): void $command
	 * @link https://symfony.com/doc/current/console/lazy_commands.html
	 */
	protected function useFoundCommand( string $classname, callable $command, string $commandName ): void {
		/** @disregard P1013 Undefined method 'set' */
		method_exists( $this->container, 'set' ) && $this->container->set( $classname, $command );
	}

	protected function getRootPath(): string {
		return $this->base['dirpath'];
	}

	protected function forCurrentFile(): void {
		if ( ! $commandClass = $this->fromFilePathToPsr4SpecificationFullyQualifiedClassName() ) {
			return;
		}

		if ( ! is_a( $commandClass, Console::class, allow_string: true ) ) {
			return;
		}

		$lazyload                       = [ $commandClass, 'start' ];
		$commandName                    = $commandClass::asCommandName();
		$this->commands[ $commandName ] = $commandClass;

		$this->useFoundCommand( $commandClass, $lazyload, $commandName );

		// Allow developers to listen for resolved command by Command Loader with the "EventTask".
		if ( $commandRunner = $this->getCommandRunnerFromEvent() ) {
			$commandRunner( new EventTask( $lazyload( ... ), $commandClass, $commandName, $this->container ) );
		}
	}

	private static function getInstance( bool $event = false ): static {
		return new static( dispatcher: $event ? new EventDispatcher() : null );
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

	private function startScan(): static {
		array_walk( $this->namespacedDirectory, $this->scanBaseDirectory( ... ) );

		$this->getApp()->setCommandLoader( new ContainerCommandLoader( $this->container, $this->commands ) );

		return $this;
	}

	/** @param array<string,string> $pathAndNamespace */
	private function scanBaseDirectory( array $pathAndNamespace ): void {
		$dirpath    = $pathAndNamespace[ $namespace = (string) array_key_first( $pathAndNamespace ) ];
		$this->base = compact( 'dirpath', 'namespace' );

		$this->scan( $dirpath );
	}

	/** @return ?callable(EventTask): void */
	private function getCommandRunnerFromEvent(): ?callable {
		return $this->dispatcher?->dispatch( new AfterLoadEvent() )->getCommandRunner();
	}
}
