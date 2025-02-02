<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Attribute;
use BackedEnum;
use TheWebSolver\Codegarage\Cli\PureArg;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Completion\Suggestion;
use TheWebSolver\Codegarage\Cli\Traits\InputProperties;
use TheWebSolver\Codegarage\Cli\Traits\ConstructorAware;
use Symfony\Component\Console\Completion\CompletionInput;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Positional {
	/** @use ConstructorAware<'name'|'desc'|'isVariadic'|'isOptional'|'default'|'suggestedValues'> */
	use InputProperties, PureArg, ConstructorAware;

	/** @var int-mask-of<InputArgument::*> The input mode. */
	public readonly int $mode;
	/** @var null|string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{}) */
	private mixed $userDefault;

	/**
	 * @param string $name       The argument name.
	 * @param string $desc       The short description about the argument.
	 * @param bool   $isVariadic Whether the argument can be repeated or not.Repeated args'll
	 *                           be converted to an array. Defaults to `false`.
	 * @param bool   $isOptional Whether the argument can be omitted. Defaults to `true`.
	 * @param (
	 *   string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{})
	 * ) $default         The argument's default value.
	 * @param (
	 *   class-string<BackedEnum>|array<string|int>|callable(CompletionInput): list<string|Suggestion>
	 * ) $suggestedValues The argument's suggested values.
	 */
	public function __construct(
		string $name,
		string $desc = null,
		bool $isVariadic = null,
		bool $isOptional = null,
		string|bool|int|float|array|callable $default = null,
		string|array|callable $suggestedValues = null
	) {
		$this->discoverPureFrom( methodName: __FUNCTION__, values: func_get_args() );

		$this->userDefault = $default;

		/** @disregard P1056 */ $this->name       = $name;
		/** @disregard P1056 */ $this->desc       = $desc ?? '';
		/** @disregard P1056 */ $this->isVariadic = $isVariadic ?? false;
		/** @disregard P1056 */ $this->isOptional = $isOptional ?? true;

		$this->mode = $this->normalizeMode();

		/** @disregard P1056 */ $this->default         = $this->normalizeDefault( $default );
		/** @disregard P1056 */ $this->suggestedValues = Parser::parseInputSuggestion( $suggestedValues ?? array() );
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

	public function __toString(): string {
		return $this->name;
	}

	/** @return array<'name'|'desc'|'isVariadic'|'isOptional'|'default'|'suggestedValues',mixed> */
	public function __debugInfo(): array {
		return $this->mapConstructor( withParamNames: true );
	}

	/**
	 * @param array{
	 *  desc?:string, isVariadic?:bool, isOptional?:bool, default?:string,
	 *  suggestedValues?: class-string<BackedEnum>|array<string|int,string|int>|callable(CompletionInput): list<string|Suggestion>
	 * } $args
	 */
	public function with( array $args ): self {
		return $this->selfFrom( $args );
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
