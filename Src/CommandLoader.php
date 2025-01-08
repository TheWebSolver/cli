<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Closure;
use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;

class CommandLoader {
	use DirectoryScanner;

	final public const COMMAND_DIRECTORY = 'Command';

	/**
	 * @param array<class-string<Console>>    $classNames
	 * @param array<string,Closure():Console> $commands
	 */
	private function __construct( private array $classNames = array(), private array $commands = array() ) {
		$this->register();

		// TODO: add subscriber for autocompletion.
	}

	/** @return string[] */
	public function getFileNames(): array {
		return array_keys( $this->scannedFiles );
	}

	/** @return array<class-string<Console>> */
	public function getClassNames(): array {
		return $this->classNames;
	}

	public static function run( bool $usingEvent = false ): self {
		$runner = new self();

		// TODO: Use Event Dispatcher here.
		// 1: Dispatch "$event" so third-party can run their own command. Makes the CLI app to be extendible.
		// 2: Use "$command = Cli::app()->get($commandName)" within listener to get loaded command.
		// 3: Then, use the "$commandName" and "$command" to run the loaded command.
		// 4: Inform back if event is used. "$usingEvent = $event->isFired()".
		// 5: Remove accepted param and use step:4 variable for check.
		// 6: Finally, run CLI app if event is not being used.
		if ( ! $usingEvent ) {
			Cli::app()->run();
		}

		return $runner;
	}

	/** @return string[] */
	private function register(): array {
		$this->scan( realpath( Cli::ROOT . self::COMMAND_DIRECTORY ) ?: '' );

		Cli::app()->setCommandLoader( new FactoryCommandLoader( $this->commands ) );

		return $this->classNames;
	}

	protected function isIgnored( string $filename ): bool {
		return ! $filename;
	}

	protected function executeFor( string $filename, string $filepath ): void {
		$classname = Cli::NAMESPACE . '\\' . self::COMMAND_DIRECTORY . "\\{$filename}";

		if ( ! is_a( $classname, Console::class, allow_string: true ) ) {
			return;
		}

		$commandName        = $classname::asCommandName();
		$this->classNames[] = $classname;

		/**
		 * Defer Symfony command instantiation until current command is ran.
		 *
		 * @see \Symfony\Component\Console\Application::setCommandLoader
		 * @link https://symfony.com/doc/current/console/lazy_commands.html
		 */
		$this->commands[ $commandName ] = $classname::instantiate( ... );
	}
}
