<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Closure;
use Attribute;
use BackedEnum;
use TheWebSolver\Codegarage\Cli\PureArg;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Associative {
	use PureArg;

	/** @var int-mask-of<InputOption::*> The input mode. */
	public readonly int $mode;
	/** @var string The short description about the option. */
	public readonly string $desc;
	/** @var bool Whether the option can be repeated or not. Eg: `--path=first/dir/ --path=next/dir/`. Defaults to `false`. */
	public readonly bool $isVariadic;
	/** @var bool Whether the option's value can be omitted. Meaning, value may or may not be passed. Eg: '--show` or `--show=yes". Defaults to `false`. */
	public readonly bool $isOptional;
	/** @var null|string|string[] The option's shortcut. For eg: "-s" for "--show". */
	public readonly null|string|array $shortcut;
	/** @var array<string|int>|(Closure(CompletionInput): list<string|Suggestion>) The option's suggested values. */
	public readonly array|Closure $suggestedValues;
	/** @var null|string|bool|int|float|array{} The option's default value. */
	public readonly null|string|bool|int|float|array $default;

	/** @var null|string|bool|int|float|array{}|class-string<BackedEnum>|(callable(): string|bool|int|float|array{}) */
	private mixed $userDefault;

	/**
	 * @param string                                                                                             $name            The option name. Eg: "show".
	 * @param string                                                                                             $desc            The short description about the option.
	 * @param bool                                                                                               $isVariadic      Whether the option can be repeated or not.
	 *                                                                                                                            Eg: `--path=first/dir/ --path=next/dir/`.
	 *                                                                                                                            Defaults to `false`.
	 * @param bool                                                                                               $isOptional      Whether the option's value can be omitted.
	 *                                                                                                                            Meaning, value may or may not be passed.
	 *                                                                                                                            Eg: '--show` or `--show=yes".
	 *                                                                                                                            Defaults to `false`.
	 * @param string|bool|int|float|array{}|class-string<BackedEnum>|(callable(): string|bool|int|float|array{}) $default         The option's default value.
	 * @param string|string[]                                                                                    $shortcut        Shortcut. For eg: "-s" for "--show".
	 * @param class-string<BackedEnum>|array<string|int>|(callable(CompletionInput): list<string|Suggestion>)    $suggestedValues The option's suggested values.
	 */
	public function __construct(
		public readonly string $name,
		string $desc = null,
		bool $isVariadic = null,
		bool $isOptional = null,
		string|bool|int|float|array|callable $default = null,
		string|array $shortcut = null,
		string|array|callable $suggestedValues = null,
	) {
		$pure = compact( 'name' );

		foreach ( array( 'desc', 'isVariadic', 'isOptional', 'default', 'shortcut', 'suggestedValues' ) as $prop ) {
			$this->collectPure( $prop, $$prop, $pure );
		}

		$this->setPure( $pure );

		$this->desc        = $desc ?? '';
		$this->shortcut    = $shortcut;
		$this->isVariadic  = $isVariadic ?? false;
		$this->isOptional  = $isOptional ?? false;
		$this->userDefault = $default;

		$this->mode            = $this->normalizeMode();
		$this->default         = $this->normalizeDefault( $default );
		$this->suggestedValues = Parser::parseInputSuggestion( $suggestedValues ?? array() );
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
			'shortcut'        => $this->shortcut,
			'suggestedValues' => $this->suggestedValues,
		);
	}

	/**
	 * @param array{
	 *  desc?:            string,
	 *  isVariadic?:      bool,
	 *  isOptional?:      bool,
	 *  default?:         string,
	 *  shortcut?:        string|string[],
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
			shortcut: $args['shortcut'] ?? $this->shortcut,
			suggestedValues: $args['suggestedValues'] ?? $this->suggestedValues
		);
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

		$this->isVariadic && ( $mode |= InputOption::VALUE_IS_ARRAY );

		return $mode;
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
			is_scalar( $value ), is_array( $value ) =>  $value,
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
