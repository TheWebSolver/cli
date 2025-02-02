<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Attribute;
use BackedEnum;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Completion\Suggestion;
use TheWebSolver\Codegarage\Cli\Traits\InputProperties;
use Symfony\Component\Console\Completion\CompletionInput;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Positional {
	/** @use InputProperties<'name'|'desc'|'isVariadic'|'isOptional'|'default'|'suggestedValues'> */
	use InputProperties;

	/** @var int-mask-of<InputArgument::*> The input mode. */
	public readonly int $mode;

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

	/**
	 * @param array{
	 *  desc?:string, isVariadic?:bool, isOptional?:bool, default?:null|string|bool|int|float|array{},
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

		return $this->isVariadic ? $mode |= InputArgument::IS_ARRAY : $mode;
	}

	/** @return null|string|bool|int|float|array{} */
	private function normalizeDefault( mixed $value ): null|string|bool|int|float|array {
		return match ( true ) {
			default               => $this->isVariadic ? array() : null,
			! $this->isOptional   => null,
			is_callable( $value ) => $this->normalizeDefault( $value() ),
			$this->isVariadic     => is_array( $value ) ? $value : ( $this->variadicFromEnum( $value ) ?? array() ),
			is_string( $value )   => Parser::parseBackedEnumValue( $value ),
			is_scalar( $value )   => $value
		};
	}
}
