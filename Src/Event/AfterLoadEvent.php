<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Event;

use TheWebSolver\Codegarage\Cli\Console;

class AfterLoadEvent {
	/** @var ?callable(string, callable():Console, class-string<Console>): void */
	private $commandRunner;

	/**
	 * @param callable(string, callable():Console, class-string<Console>): void $runner
	 * @listener
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function runCommand( callable $runner ): void {
		$this->commandRunner = $runner;
	}

	/**
	 * @return ?callable(string, callable():Console, class-string<Console>): void
	 * @dispatcher
	 */
	public function getCommandRunner(): ?callable {
		return $this->commandRunner ?? null;
	}
}
