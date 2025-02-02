<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Attribute;
use BackedEnum;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Completion\Suggestion;
use TheWebSolver\Codegarage\Cli\Traits\InputProperties;
use Symfony\Component\Console\Completion\CompletionInput;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Associative {
	/** @use InputProperties<'name'|'desc'|'isVariadic'|'isOptional'|'default'|'shortcut'|'suggestedValues'> */
	use InputProperties {
		InputProperties::__construct as constructor;
	}

	/** @var int-mask-of<InputOption::*> The input mode. */
	public readonly int $mode;
	/** @var null|string|string[] The option's shortcut. For eg: "-s" for "--show". */
	public readonly null|string|array $shortcut;

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
		/** @var array<'name'|'desc'|'isVariadic'|'isOptional'|'default'|'shortcut'|'suggestedValues'> */
		$names            = $this->discoverPureFrom( methodName: __FUNCTION__, values: func_get_args() );
		$this->paramNames = $names;
		$this->shortcut   = $shortcut;

		$this->constructor( $name, $desc, $isVariadic, $isOptional, $default, $suggestedValues );
	}

	private function isOptional(): bool {
		return false;
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

	/**
	 * @param array{
	 *   desc?:string, isVariadic?:bool, isOptional?:bool, default?:null|string|bool|int|float|array{}, shortcut?:string|string[],
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

	/** @return null|string|bool|int|float|array{} */
	private function normalizeDefault( mixed $value ): null|string|bool|int|float|array {
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
		return is_array( $value ) ? $value : ( $this->variadicFromEnum( $value ) ?? array() );
	}
}
