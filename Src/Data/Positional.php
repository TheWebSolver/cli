<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Attribute;
use Symfony\Component\Console\Input\InputArgument;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Positional {
	/** @var int-mask-of<InputArgument::*> The input mode. */
	public int $mode;

	/**
	 * @param string                             $name       The argument name.
	 * @param string                             $desc       The short description what argument should be used for.
	 * @param bool                               $isVariadic Whether the argument can be repeated or not.
	 *                                                       Repeated args'll be converted to an array.
	 * @param bool                               $isOptional Whether the argument can be omitted.
	 * @param null|string|bool|int|float|array{} $default    The default value, if any.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Int mask for mode OK.
	public function __construct(
		public string $name,
		public string $desc,
		public bool $isVariadic = false,
		public bool $isOptional = true,
		public null|string|bool|int|float|array $default = null,
	) {
		$this->resolveMode();
	}

	private function resolveMode(): void {
		$this->mode = $this->isOptional ? InputArgument::OPTIONAL : InputArgument::REQUIRED;

		if ( $this->isVariadic ) {
			$this->mode |= InputArgument::IS_ARRAY;
		}
	}
}
