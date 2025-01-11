<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Event;

use TheWebSolver\Codegarage\Cli\Data\EventTask;
class AfterLoadEvent {
	/** @var ?callable(EventTask): void */
	private $commandRunner;

	/**
	 * @param callable(EventTask): void $runner
	 * @listener
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function runCommand( callable $runner ): void {
		$this->commandRunner = $runner;
	}

	/**
	 * @return ?callable(EventTask): void
	 * @dispatcher
	 */
	public function getCommandRunner(): ?callable {
		return $this->commandRunner ?? null;
	}
}
