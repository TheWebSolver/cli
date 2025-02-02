<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Attribute;
use BackedEnum;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use TheWebSolver\Codegarage\Cli\Traits\PureArg;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Completion\Suggestion;
use TheWebSolver\Codegarage\Cli\Traits\InputProperties;
use TheWebSolver\Codegarage\Cli\Traits\ConstructorAware;
use Symfony\Component\Console\Completion\CompletionInput;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Associative {
	/** @use ConstructorAware<'name'|'desc'|'isVariadic'|'isOptional'|'default'|'shortcut'|'suggestedValues'> */
	use InputProperties, PureArg, ConstructorAware;

	/** @var int-mask-of<InputOption::*> The input mode. */
	public readonly int $mode;
	/** @var null|string|string[] The option's shortcut. For eg: "-s" for "--show". */
	public readonly null|string|array $shortcut;
	/** @var null|string|bool|int|float|array{}|class-string<BackedEnum>|(callable(): string|bool|int|float|array{}) */
	private mixed $userDefault;

	/**
	 * @param string          $name       The option name. Eg: "show".
	 * @param string          $desc       The short description about the option.
	 * @param bool            $isVariadic Whether the option can be repeated or not. Eg: `--path=first/dir/
	 *                                    --path=next/dir/`. Defaults to `false`.
	 * @param bool            $isOptional Whether the option's value can be omitted. Eg: '--show` or `--show=yes".
	 *                                    Defaults to `false`.
	 * @param (
	 *   string|bool|int|float|array{}|class-string<BackedEnum>|(callable(): string|bool|int|float|array{})
	 * )                      $default  The option's default value.
	 * @param string|string[] $shortcut Shortcut. For eg: "-s" for "--show".
	 * @param (
	 *   class-string<BackedEnum>|array<string|int>|(callable(CompletionInput): list<string|Suggestion>)
	 * )                      $suggestedValues The option's suggested values.
	 */
	public function __construct(
		string $name,
		string $desc = null,
		bool $isVariadic = null,
		bool $isOptional = null,
		string|bool|int|float|array|callable $default = null,
		string|array $shortcut = null,
		string|array|callable $suggestedValues = null,
	) {
		$this->paramNames  = $this->discoverPureFrom( methodName: __FUNCTION__, values: func_get_args() );
		$this->shortcut    = $shortcut;
		$this->userDefault = $default;

		/** @disregard P1056 */ $this->name       = $name;
		/** @disregard P1056 */ $this->desc       = $desc ?? '';
		/** @disregard P1056 */ $this->isVariadic = $isVariadic ?? false;
		/** @disregard P1056 */ $this->isOptional = $isOptional ?? false;

		$this->mode = $this->normalizeMode();

		/** @disregard P1056 */ $this->default         = $this->normalizeDefault( $default );
		/** @disregard P1056 */ $this->suggestedValues = Parser::parseInputSuggestion( $suggestedValues ?? array() );
	}

	public static function from( InputOption $input ): self {
		return new self(
			name: $input->getName(),
			desc: $input->getDescription(),
			isVariadic: $input->isArray(),
			isOptional: ! $input->isValueRequired(),
			default: $input->getDefault(),
			shortcut: $input->getShortcut(),
			suggestedValues: Parser::suggestedValuesFrom( $input ) ?? array()
		);
	}

	/** @return null|string|bool|int|float|array{}|class-string<BackedEnum>|(callable(): string|bool|int|float|array{}) */
	public function getUserDefault(): null|string|bool|int|float|array|callable {
		return $this->userDefault;
	}

	public function __toString(): string {
		return $this->name;
	}

	/** @return array<'name'|'desc'|'isVariadic'|'isOptional'|'default'|'shortcut'|'suggestedValues',mixed> */
	public function __debugInfo(): array {
		return $this->mapConstructor( withParamNames: true );
	}

	/**
	 * @param array{
	 *   desc?:string, isVariadic?:bool, isOptional?:bool, default?:string, shortcut?:string|string[],
	 *   suggestedValues?: class-string<BackedEnum>|array<string|int,string|int>|callable(CompletionInput): list<string|Suggestion>
	 * } $args
	 */
	public function with( array $args ): self {
		return $this->selfFrom( $args );
	}

	public function input(): InputOption {
		return new InputOption(
			$this->name,
			$this->shortcut,
			$this->mode,
			$this->desc,
			$this->default,
			$this->suggestedValues
		);
	}

	/** @return int-mask-of<InputOption::*> */
	private function normalizeMode(): int {
		$mode = $this->isOptional ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_REQUIRED;

		return $this->isVariadic ? $mode |= InputOption::VALUE_IS_ARRAY : $mode;
	}

	/**
	 * @param mixed $value
	 * @return null|string|bool|int|float|array{}
	 */
	private function normalizeDefault( $value ): null|string|bool|int|float|array {
		return match ( true ) {
			default                                 => $this->isVariadic ? array() : null,
			$this->isOptionalDefault( $value )      => $this->isVariadic ? array() : '',
			is_callable( $value )                   => $this->normalizeDefault( $value() ),
			$this->isVariadic                       => $this->getVariadicDefault( $value ),
			is_string( $value )                     => Parser::parseBackedEnumValue( $value ),
			is_scalar( $value ), is_array( $value ) => $value,
		};
	}

	private function isOptionalDefault( mixed $value ): bool {
		return $this->isOptional && null === $value;
	}

	/** @return array{} */
	private function getVariadicDefault( mixed $value ): array {
		return is_array( $value ) ? $value : ( Positional::variadicFromEnum( $value ) ?? array() );
	}
}
