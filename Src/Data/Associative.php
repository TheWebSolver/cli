<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Closure;
use Attribute;
use BackedEnum;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
readonly class Associative {
	/** @var int-mask-of<InputOption::*> */
	public int $mode;

	/** @var array<string|int>|(Closure(CompletionInput): list<string|Suggestion>) The option's suggested values. */
	public array|Closure $suggestedValues;

	/** @var null|string|bool|int|float|array{} */
	public null|string|bool|int|float|array $default;

	/** @var null|string|bool|int|float|array{}|class-string<BackedEnum>|(callable(): string|bool|int|float|array{}) */
	private mixed $userDefault;

	/**
	 * @param string                                                                                                  $name            The option name. Eg: "show".
	 * @param string                                                                                                  $desc            The short description about the option.
	 * @param bool                                                                                                    $isVariadic      Whether the option can be repeated or not.
	 *                                                                                                                                 Eg: `--path=first/dir/ --path=next/dir/`.
	 * @param bool                                                                                                    $valueOptional   Whether the option's value can be omitted.
	 *                                                                                                                                 Meaning, value may or may not be passed.
	 *                                                                                                                                 Eg: '--show` or `--show=yes".
	 * @param null|string|bool|int|float|array{}|class-string<BackedEnum>|(callable(): string|bool|int|float|array{}) $default         The option's default value.
	 * @param null|string|array<string|callable>                                                                      $shortcut        Shortcut. For eg: "-s" for "--show".
	 * @param class-string<BackedEnum>|array<string|int>|(callable(CompletionInput): list<string|Suggestion>)         $suggestedValues The option's suggested values.
	 */
	public function __construct(
		public string $name,
		public string $desc = '',
		public bool $isVariadic = false,
		public bool $valueOptional = false,
		null|string|bool|int|float|array|callable $default = null,
		public null|string|array $shortcut = null,
		string|array|callable $suggestedValues = array(),
	) {
		$this->normalizeMode();

		$this->default         = $this->normalizeDefault( $default );
		$this->userDefault     = $default;
		$this->suggestedValues = Parser::parseInputSuggestion( $suggestedValues );
	}

	public static function from( InputOption $input ): self {
		return new self(
			name: $input->getName(),
			desc: $input->getDescription(),
			isVariadic: $input->isArray(),
			valueOptional: ! $input->isValueRequired(),
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
			'valueOptional'   => $this->valueOptional,
			'default'         => $this->userDefault,
			'shortcut'        => $this->shortcut,
			'suggestedValues' => $this->suggestedValues,
		);
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName
	/**
	 * @param array{
	 *  desc?:            string,
	 *  isVariadic?:      bool,
	 *  valueOptional?:   bool,
	 *  default?:         string,
	 *  shortcut?:        string|string[],
	 *  suggestedValues?: class-string<BackedEnum>|array<string|int,string|int>|callable(CompletionInput): list<string|Suggestion>
	 * } $args
	 */
	// phpcs:enable
	public function with( array $args ): self {
		return new self(
			name: $this->name,
			desc: $args['desc'] ?? $this->desc,
			isVariadic: $args['isVariadic'] ?? $this->isVariadic,
			valueOptional: $args['valueOptional'] ?? $this->valueOptional,
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

	private function normalizeMode(): void {
		$mode = $this->valueOptional ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_REQUIRED;

		if ( $this->isVariadic ) {
			$mode |= InputOption::VALUE_IS_ARRAY;
		}

		$this->mode = $mode;
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
		return $this->valueOptional && null === $value;
	}

	/** @return array{} */
	private function getVariadicDefault( mixed $value ): array {
		return is_array( $value ) ? $value : ( Positional::variadicFromEnum( $value ) ?? array() );
	}
}
