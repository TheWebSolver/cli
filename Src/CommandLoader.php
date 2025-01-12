<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use LogicException;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\EventTask;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Cli\Event\AfterLoadEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TheWebSolver\Codegarage\Cli\Event\CommandSubscriber;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

class CommandLoader {
	use DirectoryScanner;

	final public const COMMAND_DIRECTORY = Cli::ROOT . 'Command';

	/**
	 * @param Container                           $container
	 * @param array{0:string,1:string}            $registeredDirAndNamespace
	 * @param array<string,class-string<Console>> $commands
	 */
	private function __construct(
		private Container $container,
		private array $registeredDirAndNamespace,
		private array $commands = array(),
		private ?EventDispatcher $dispatcher = null
	) {
		$container->get( Cli::class )->eventDispatcher()->addSubscriber( new CommandSubscriber() );
	}

	public function getContainer(): Container {
		return $this->container;
	}

	/** @return array<string,string> List of found filePaths indexed by filename. */
	public function getFileNames(): array {
		return $this->scannedFiles;
	}

	/** @return array<string,class-string<Console>> */
	public function getCommands(): array {
		return $this->commands;
	}

	public static function subscribe(): self {
		return self::getInstance( self::COMMAND_DIRECTORY, Cli::NAMESPACE, event: true );
	}

	public function toLocation( string $directory, string $ns ): self {
		$this->registeredDirAndNamespace = array( $directory, $ns );

		return $this;
	}

	/** @param callable(EventTask): void $listener */
	public function withListener( callable $listener ): self {
		if ( $this->dispatcher ) {
			$this->dispatcher->addListener(
				AfterLoadEvent::class,
				static fn( AfterLoadEvent $e ) => $e->runCommand( $listener( ... ) )
			);

			$this->startScan();
		}

		return $this;
	}

	public static function run(
		string $directory = self::COMMAND_DIRECTORY,
		string $ns = Cli::NAMESPACE,
		/* bool $runApplication = true: Runs Symfony Application by default. */
	): self {
		$loader = self::getInstance( $directory, $ns, event: false );

		$loader->startScan();

		if ( true === ( func_num_args() >= 3 ? func_get_arg( position: 2 ) : true ) ) {
			$loader->container->get( Cli::class )->run();
		}

		return $loader;
	}

	private static function getInstance( string $dir, string $ns, bool $event = false ): self {
		return new self( Container::boot(), array( $dir, $ns ), dispatcher: $event ? new EventDispatcher() : null );
	}

	private function startScan(): void {
		$this->scan( realpath( $this->registeredDirAndNamespace[0] ) ?: $this->throwInvalidDir() );

		// By default, all lazy-loaded commands extending Console will use default "Cli".
		// Different application maybe used with setter "Console::setApplication()".
		$this->container
			->get( Cli::class )
			->setCommandLoader( new ContainerCommandLoader( $this->container, $this->commands ) );
	}

	protected function isIgnored( string $filename ): bool {
		return ! $filename;
	}

	protected function executeFor( string $filename, string $filepath ): void {
		$command = "{$this->registeredDirAndNamespace[1]}\\{$filename}";

		if ( ! is_a( $command, Console::class, allow_string: true ) ) {
			return;
		}

		$lazyload                       = array( $command, 'start' );
		$commandName                    = $command::asCommandName( $this->container );
		$this->commands[ $commandName ] = $command;

		$this->handleResolved( $command, $lazyload, $commandName );

		/**
		 * Allow third-party to listen for resolved command by Command Loader.
		 * It is the developer's responsibility to run the Application by
		 * themselves using "$commandRunner" with "EventTask".
		 */
		if ( $commandRunner = $this->getCommandRunnerFromEvent() ) {
			$commandRunner( new EventTask( $lazyload( ... ), $command, $commandName, $this->container ) );
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

	/** @return ?callable(EventTask): void */
	private function getCommandRunnerFromEvent(): ?callable {
		return $this->dispatcher?->dispatch( new AfterLoadEvent() )->getCommandRunner();
	}

	private function throwInvalidDir(): never {
		throw new LogicException(
			sprintf( 'Cannot locate commands in directory: "%s".', $this->registeredDirAndNamespace[0] )
		);
	}
}
