<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Event;

use Closure;
use TheWebSolver\Codegarage\Cli\CommandLoader;

class BeforeRunEvent {
	/** @var (Closure(CommandLoader): void)[] */
	private array $commandLoader;

	/**
	 * @param callable(CommandLoader): void $loader The callable accepts the command loader instance.
	 * @listener
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function runCommand( callable $loader ): void {
		$this->commandLoader[] = $loader( ... );
	}

	/**
	 * @return (Closure(CommandLoader): void)[]
	 * @dispatcher
	 */
	public function getCommandLoader(): ?array {
		return $this->commandLoader ?? null;
	}
}
