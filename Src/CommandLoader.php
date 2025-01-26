<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Countable;
use LogicException;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\EventTask;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Cli\Event\AfterLoadEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TheWebSolver\Codegarage\Cli\Event\CommandSubscriber;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

class CommandLoader implements Countable {
	use DirectoryScanner {
		DirectoryScanner::scan as doScan;
	}

	final public const COMMAND_DIRECTORY = Cli::ROOT . 'Command';

	/** @var array{0:string,1:string} */
	protected array $currentMap;
	protected string $rootPath;
	private bool $scanStarted;

	/**
	 * @param Container                           $container
	 * @param array<array{0:string,1:string}>     $registeredDirAndNamespace
	 * @param array<string,class-string<Console>> $commands
	 */
	final private function __construct(
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

	/** @return array<array{0:string,1:string}> List of directory name and its command namespace. */
	public function getDirectoryNamespaceMap(): array {
		return $this->registeredDirAndNamespace;
	}

	/** @return array<string,string> List of found dirnames/filenames indexed by its absolute path. */
	public function getScannedItems(): array {
		return $this->scannedFiles;
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
	 * @param array<string,int|int[]> $nameWithDepth Sub-directory name and its depth/depths (if same name in nested depths)
	 *                                               whose command files should be scanned and loaded.
	 */
	public static function withSubdirectories( array $nameWithDepth, ?Container $container = null ): static {
		return self::getInstance( $container )->usingDirectories( $nameWithDepth );
	}

	/** @param array{0:string,1:string}[] $dirNamespaceMaps */
	public static function load( array $dirNamespaceMaps, ?Container $container = null ): static {
		return self::getInstance( $container, $dirNamespaceMaps, event: false )->startScan();
	}

	public static function subscribe( ?Container $container = null ): static {
		return self::getInstance( $container, event: true );
	}

	/**
	 * @param array{0:string,1:string} $dirNamespaceMap     The directory name as key and namespace as value.
	 * @param array{0:string,1:string} ...$dirNamespaceMaps Additional directory name and namespace map.
	 */
	public function forLocation( array $dirNamespaceMap, array ...$dirNamespaceMaps ): static {
		$this->registerLocation( $dirNamespaceMap );

		array_walk( $dirNamespaceMaps, $this->registerLocation( ... ) );

		return $this;
	}

	/** @param callable(EventTask): void $listener */
	public function withListener( callable $listener ): static {
		if ( $this->dispatcher ) {
			$this->dispatcher->addListener(
				AfterLoadEvent::class,
				static fn( AfterLoadEvent $e ) => $e->runCommand( $listener( ... ) )
			);

			$this->startScan();
		}

		return $this;
	}

	public function scan(): static {
		return $this->startScan();
	}

	/** @param array{0:string,1:string}[] $map */
	private static function getInstance( ?Container $c, array $map = array(), bool $event = false ): static {
		$c ??= Container::boot();

		return new static( $c, $map, dispatcher: $event ? new EventDispatcher() : null );
	}

	protected function scannableDirectory(): void {
		$item = $this->currentItem();

		$this->scanCurrent( array( $item->getPathname(), $this->currentMap[1] . "\\{$item->getFilename()}" ) );
	}

	protected function getRootPath(): string {
		return $this->rootPath;
	}

	protected function executeFor( string $filename, string $filepath ): void {
		$command = "{$this->currentMap[1]}\\{$filename}";

		if ( ! is_a( $command, Console::class, allow_string: true ) ) {
			return;
		}

		$lazyload                       = array( $command, 'start' );
		$commandName                    = $command::asCommandName();
		$this->commands[ $commandName ] = $command;

		$this->handleResolved( $command, $lazyload, $commandName );

		// Allow third-party to listen for resolved command by Command Loader  with the "EventTask".
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

	/** @param array{0:string,1:string} $dirNamespaceMap */
	private function registerLocation( array $dirNamespaceMap ): void {
		$this->registeredDirAndNamespace[] = $dirNamespaceMap;
	}

	private function startScan(): static {
		if ( $this->scanStarted ?? false ) {
			return $this;
		}

		$this->scanStarted = true;

		foreach ( $this->registeredDirAndNamespace as $index => $map ) {
			if ( array_key_first( $this->registeredDirAndNamespace ) === $index ) {
				$this->rootPath = $map[ $index ];
			}

			$this->scanCurrent( $map );
		}

		// By default, all lazy-loaded commands extending Console will use default "Cli".
		// Different application maybe used with setter "Console::setApplication()".
		$this->container
			->get( Cli::class )
			->setCommandLoader( new ContainerCommandLoader( $this->container, $this->commands ) );

		return $this;
	}

	/** @param array{0:string,1:string} $dirNamespaceMap */
	protected function scanCurrent( array $dirNamespaceMap ): static {
		$this->currentMap = $dirNamespaceMap;

		return $this->doScan( realpath( $dirNamespaceMap[0] ) ?: $this->throwInvalidDir() );
	}

	/** @return ?callable(EventTask): void */
	private function getCommandRunnerFromEvent(): ?callable {
		return $this->dispatcher?->dispatch( new AfterLoadEvent() )->getCommandRunner();
	}

	private function throwInvalidDir(): never {
		throw new LogicException( sprintf( 'Cannot locate commands in directory: "%s".', $this->currentMap[0] ) );
	}
}
