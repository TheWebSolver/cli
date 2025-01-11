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

	/** @var int-mask-of<InputOption::*> */
	public int $mode;

	/**
	 * @param string                             $name            The option name. Eg: "show".
	 * @param string                             $desc            The short description about the option.
	 * @param bool                               $isVariadic      Whether the option can be repeated or not. Eg:
	 *                                                            `--path=some/dir/ --path=another/dir/`.
	 * @param bool                               $valueOptional   Whether option's value can be omitted. Meaning it's value
	 *                                                            or may not be passed. Eg: '--show` or `--show=yes".
	 * @param null|string|bool|int|float|array{} $default         The option's default value. When `$valueOptional` is set
	 *                                                            to `true`, the default value will be set to `false`.
	 * @param null|string|array<string|callable> $shortcut        Shortcut. For eg: "-s" for "--show".
	 * @param class-string<BackedEnum>|array{}   $suggestedValues The suggested values, if any.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Int mask for mode OK.
	public function __construct(
		public string $name,
		public string $desc,
		public bool $isVariadic = false,
		public bool $valueOptional = false,
		public null|string|bool|int|float|array $default = null,
		public null|string|array $shortcut = null,
		string|array|callable $suggestedValues = array(),
	) {
		$this->normalizeMode();
		$this->normalizeSuggestion( $suggestedValues );
	}

	protected function normalizeMode(): void {
		$this->mode = InputOption::VALUE_REQUIRED;

		if ( $this->valueOptional ) {
			$this->default = false;
			$this->mode    = InputOption::VALUE_OPTIONAL;
		}

		if ( $this->isVariadic ) {
			$this->mode |= InputOption::VALUE_IS_ARRAY;
		}
	}

	/** @param array{}|callable|string $value */
	protected function normalizeSuggestion( array|callable|string $value ): void {
		$this->suggestedValues = match ( true ) {
			default                                 => array(),
			is_array( $value )                      => $value,
			is_callable( $value )                   => $value( ... ),
			is_a( $value, BackedEnum::class, true ) => array_column( $value::cases(), 'value' )
		};
	}
}
