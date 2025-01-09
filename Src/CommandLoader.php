<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Closure;
use LogicException;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Event\BeforeRunEvent;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

class CommandLoader {
	use DirectoryScanner;

	final public const COMMAND_DIRECTORY = 'Command';

	/**
	 * @param array{0:string,1:string}        $registeredDirAndNamespace
	 * @param class-string<Console>[]         $classNames
	 * @param array<string,Closure():Console> $commands
	 */
	private function __construct(
		private array $registeredDirAndNamespace,
		private array $classNames = array(),
		private array $commands = array()
	) {
		$this->startScan();

		// TODO: add subscriber for autocompletion.
	}

	/** @return array<string,string> List of found filePaths indexed by filename. */
	public function getFileNames(): array {
		return $this->scannedFiles;
	}

	/** @return class-string<Console>[] */
	public function getClassNames(): array {
		return $this->classNames;
	}

	/** @return array<string,Closure():Console> */
	public function getLazyLoadedCommands(): array {
		return $this->commands;
	}

	public static function run(
		string $directory = Cli::ROOT . self::COMMAND_DIRECTORY,
		string $ns = Cli::NAMESPACE
	): self {
		$loader = new self( array( $directory, $ns ) );
		$event  = Cli::app()->eventDispatcher()->dispatch( new BeforeRunEvent() );

		if ( $commandLoaders = $event->getCommandLoader() ) {
			foreach ( $commandLoaders as $commandLoader ) {
				// Allow listeners to use scanned files, classnames and lazy-loaded commands.
				$commandLoader( $loader );
			}
		} else {
			Cli::app()->run();
		}

		return $loader;
	}

	private function startScan(): void {
		$this->scan( realpath( $this->registeredDirAndNamespace[0] ) ?: $this->throwInvalidDir() );

		// By default, all lazy-loaded commands extending Console will use the Cli::app().
		// By overriding Console::setCliApp(), different application may be used.
		Cli::app()->setCommandLoader( new FactoryCommandLoader( $this->commands ) );
	}

	protected function isIgnored( string $filename ): bool {
		return ! $filename;
	}

	protected function executeFor( string $filename, string $filepath ): void {
		$command = "\\{$this->registeredDirAndNamespace[1]}\\{$filename}";

		if ( ! is_a( $command, Console::class, allow_string: true ) ) {
			return;
		}

		$commandName        = $command::asCommandName();
		$this->classNames[] = $command;

		/**
		 * Defer Symfony command instantiation until current command is ran.
		 *
		 * @see \Symfony\Component\Console\Application::setCommandLoader
		 * @link https://symfony.com/doc/current/console/lazy_commands.html
		 */
		$this->commands[ $commandName ] = $command::start( ... );
	}

	private function throwInvalidDir(): never {
		throw new LogicException(
			sprintf( 'Cannot locate commands in directory: "%s".', $this->registeredDirAndNamespace[0] )
		);
	}
}
