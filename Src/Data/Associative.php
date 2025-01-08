<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Symfony\Component\Console\Input\InputOption;

final readonly class Associative {
	// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/**
	 * @param array<mixed>                       $options
	 * @param null|string|array<string|callable> $shortcut
	 * @param null|string|bool|int|float|array{} $default
	 * @param int-mask-of<InputOption::*>        $mode
	 */
	// phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function __construct(
		public string $name,
		public string $desc,
		public null|string|bool|int|float|array $default = null,
		public int $mode = InputOption::VALUE_REQUIRED,
		public null|string|array $shortcut = null,
		public array $options = array(),
	) {}
}
