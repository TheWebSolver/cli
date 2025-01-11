<?php
declare( strict_types = 1);

namespace TheWebSolver\Codegarage\Cli\Data;

use Closure;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Container\Container;

/**
 * @param Closure(?Container): Console $command
 * @param class-string<Console>        $className
 */
readonly class EventTask {
	public function __construct(
		public Closure $command,
		public string $className,
		public string $commandName,
		public Container $container
	) {}
}
