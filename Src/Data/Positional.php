<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Closure;
use Attribute;
use BackedEnum;
use TheWebSolver\Codegarage\Cli\PureArg;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Positional {
	use PureArg;

	/** @var int-mask-of<InputArgument::*> The input mode. */
	public readonly int $mode;

	/** @var array<string|int>|Closure(CompletionInput): list<string|Suggestion> The argument's suggested values. */
	public readonly array|Closure $suggestedValues;

	/** @var null|string|bool|int|float|array{} */
	public readonly null|string|bool|int|float|array $default;

	/** @var null|string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{}) */
	private mixed $userDefault;

	/**
	 * @param string                                                                                                  $name            The argument name.
	 * @param string                                                                                                  $desc            The short description about the argument.
	 * @param bool                                                                                                    $isVariadic      Whether the argument can be repeated or not.
	 *                                                                                                                                 Repeated args'll be converted to an array.
	 * @param bool                                                                                                    $isOptional      Whether the argument can be omitted.
	 * @param null|string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{}) $default         The argument's default value.
	 * @param class-string<BackedEnum>|array<string|int>|callable(CompletionInput): list<string|Suggestion>           $suggestedValues The argument's suggested values.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $desc = '',
		public readonly bool $isVariadic = false,
		public readonly bool $isOptional = true,
		null|string|bool|int|float|array|callable $default = null,
		string|array|callable $suggestedValues = array(),
	) {
		$this->mode            = $this->setPure( func_get_args() )->normalizeMode();
		$this->default         = $this->normalizeDefault( $default );
		$this->userDefault     = $default;
		$this->suggestedValues = Parser::parseInputSuggestion( $suggestedValues );
	}

	public static function from( InputArgument $input ): self {
		return new self(
			name: $input->getName(),
			desc: $input->getDescription(),
			isVariadic: $input->isArray(),
			isOptional: ! $input->isRequired(),
			default: $input->getDefault(),
			suggestedValues: Parser::suggestedValuesFrom( $input ) ?? array()
		);
	}

	/** @return null|string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{}) */
	public function getUserDefault(): null|string|bool|int|float|array|callable {
		return $this->userDefault;
	}

	public function __toString() {
		return $this->name;
	}

	public function __debugInfo() {
		return array(
			'name'            => $this->name,
			'desc'            => $this->desc,
			'isVariadic'      => $this->isVariadic,
			'isOptional'      => $this->isOptional,
			'default'         => $this->userDefault,
			'suggestedValues' => $this->suggestedValues,
		);
	}

	/**
	 * @param array{
	 *  desc?:            string,
	 *  isVariadic?:      bool,
	 *  isOptional?:      bool,
	 *  default?:         string,
	 *  suggestedValues?: class-string<BackedEnum>|array<string|int,string|int>|callable(CompletionInput): list<string|Suggestion>
	 * } $args
	 */
	public function with( array $args ): self {
		return new self(
			name: $this->name,
			desc: $args['desc'] ?? $this->desc,
			isVariadic: $args['isVariadic'] ?? $this->isVariadic,
			isOptional: $args['isOptional'] ?? $this->isOptional,
			default: $args['default'] ?? $this->default,
			suggestedValues: $args['suggestedValues'] ?? $this->suggestedValues
		);
	}

	public function input(): InputArgument {
		return new InputArgument(
			$this->name,
			$this->mode,
			$this->desc,
			$this->default,
			$this->suggestedValues
		);
	}

	/** @return int-mask-of<InputArgument::*>  */
	private function normalizeMode(): int {
		$mode = $this->isOptional ? InputArgument::OPTIONAL : InputArgument::REQUIRED;

		$this->isVariadic && ( $mode |= InputArgument::IS_ARRAY );

		return $mode;
	}

	/**
	 * @param mixed $value
	 * @return null|string|bool|int|float|array{}
	 */
	private function normalizeDefault( $value ): null|string|bool|int|float|array {
		return match ( true ) {
			default               => $this->isVariadic ? array() : null,
			! $this->isOptional   => null,
			is_callable( $value ) => $this->normalizeDefault( $value() ),
			$this->isVariadic     => is_array( $value ) ? $value : ( self::variadicFromEnum( $value ) ?? array() ),
			is_string( $value )   => Parser::parseBackedEnumValue( $value ),
			is_scalar( $value )   => $value
		};
	}

	/** @return array<string|int> */
	public static function variadicFromEnum( mixed $maybeBackedEnumClass ): ?array {
		return is_string( $maybeBackedEnumClass )
			? ( is_array( $cases = Parser::parseBackedEnumValue( $maybeBackedEnumClass ) ) ? $cases : null )
			: null;
	}
}
