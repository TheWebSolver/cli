<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Closure;
use Attribute;
use BackedEnum;
use Symfony\Component\Console\Input\InputOption;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Associative {
	/** @var array{}|Closure The argument options. */
	public array|Closure $suggestedValues;

	/**
	 * @param string                             $name            The argument name.
	 * @param string                             $desc            The short description what argument should be used for.
	 * @param null|string|bool|int|float|array{} $default         The default value, if any.
	 * @param int-mask-of<InputOption::*>        $mode            The Input mode.
	 * @param null|string|array<string|callable> $shortcut        Shortcut. For eg: "s" for "--shout".
	 * @param class-string<BackedEnum>|array{}   $suggestedValues The suggested values, if any.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Int mask for mode OK.
	public function __construct(
		public string $name,
		public string $desc,
		public null|string|bool|int|float|array $default = null,
		public int $mode = InputOption::VALUE_REQUIRED,
		public null|string|array $shortcut = null,
		string|array|callable $suggestedValues = array(),
	) {
		$this->normalizeSuggestion( $suggestedValues );
	}

	/** @param array{}|callable|string $value */
	private function normalizeSuggestion( array|callable|string $value ): void {
		$this->suggestedValues = match ( true ) {
			default                                 => array(),
			is_array( $value )                      => $value,
			is_callable( $value )                   => $value( ... ),
			is_a( $value, BackedEnum::class, true ) => array_column( $value::cases(), 'value' )
		};
	}
}
