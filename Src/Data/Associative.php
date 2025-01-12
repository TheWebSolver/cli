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
use Symfony\Component\Console\Completion\CompletionSuggestions;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
readonly class Associative {
	/** @var int-mask-of<InputOption::*> */
	public int $mode;

	/** @var array<string|int>|(Closure(CompletionInput, CompletionSuggestions): list<string|Suggestion>) The option's suggested values. */
	public array|Closure $suggestedValues;

	/** @var null|string|bool|int|float|array{} */
	public null|string|bool|int|float|array $default;

	/**
	 * @param string                                                                                                                 $name            The option name. Eg: "show".
	 * @param string                                                                                                                 $desc            The short description about the option.
	 * @param bool                                                                                                                   $isVariadic      Whether the option can be repeated or not.
	 *                                                                                                                                                Eg: `--path=first/dir/ --path=next/dir/`.
	 * @param bool                                                                                                                   $valueOptional   Whether the option's value can be omitted.
	 *                                                                                                                                                Meaning, value may or may not be passed.
	 *                                                                                                                                                Eg: '--show` or `--show=yes".
	 * @param null|string|bool|int|float|array{}|class-string<BackedEnum>|(callable(): string|bool|int|float|array{})                $default         The option's default value.
	 * @param null|string|array<string|callable>                                                                                     $shortcut        Shortcut. For eg: "-s" for "--show".
	 * @param class-string<BackedEnum>|array<string|int>|(callable(CompletionInput, CompletionSuggestions): list<string|Suggestion>) $suggestedValues The option's suggested values.
	 */
	public function __construct(
		public string $name,
		public string $desc,
		public bool $isVariadic = false,
		public bool $valueOptional = false,
		null|string|bool|int|float|array|callable $default = null,
		public null|string|array $shortcut = null,
		string|array|callable $suggestedValues = array(),
	) {
		$this->normalizeMode();

		$this->default         = $this->normalizeDefault( $default );
		$this->suggestedValues = Parser::parseInputSuggestion( $suggestedValues );
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
			default                            => $this->isVariadic ? array() : null,
			$this->isOptionalDefault( $value ) => $this->isVariadic ? array() : false,
			is_callable( $value )              => $this->normalizeDefault( $value() ),
			$this->isVariadic                  => $this->getVariadicDefault( $value ),
			is_string( $value )                => Parser::parseBackedEnumValue( $value ),
			is_scalar( $value )                =>  $value,
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
