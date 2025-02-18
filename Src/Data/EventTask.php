<?php
declare( strict_types = 1);

namespace TheWebSolver\Codegarage\Cli\Data;

use Closure;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Cli\Console;

/**
 * @param Closure(ContainerInterface): Console $command
 * @param class-string<Console>        $className
 */
readonly class EventTask {
	public function __construct(
		public Closure $command,
		public string $className,
		public string $commandName,
		public ContainerInterface $container
	) {}
}
