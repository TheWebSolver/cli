<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Event;

use Closure;
use TheWebSolver\Codegarage\Cli\Console;

class BeforeRunEvent {
	/** @var ?callable(string $commandName, Closure():Console $command, string $className): void */
	private $commandRunner;

	/**
	 * @param callable(string $commandName, Closure():Console $command, string $className): void $loader
	 * @listener
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function runCommand( callable $loader ): void {
		$this->commandRunner = $loader;
	}

	/**
	 * @return ?callable(string $commandName, Closure():Console $command, string $className): void
	 * @dispatcher
	 */
	public function getCommandRunner(): ?callable {
		return $this->commandRunner ?? null;
	}
}
