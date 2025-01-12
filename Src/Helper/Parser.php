<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use Closure;
use BackedEnum;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

class Parser {
	/** @return ($name is class-string<BackedEnum> ? array<($caseAsIndex is true ? string : int),string|int> : string)*/
	public static function parseBackedEnumValue( string $name, bool $caseAsIndex = false ): string|array {
		return is_a( $name, BackedEnum::class, allow_string: true )
			? array_column( $name::cases(), 'value', index_key: $caseAsIndex ? 'name' : null )
			: $name;
	}

	/**
	 * @param class-string<BackedEnum>|array{}|callable(CompletionInput, CompletionSuggestions): list<string|Suggestion> $value
	 * @return array<string|int>|Closure(CompletionInput, CompletionSuggestions): list<string|Suggestion> */
	public static function parseInputSuggestion( $value ): array|Closure {
		return match ( true ) {
			is_callable( $value ) => $value( ... ),
			is_array( $value )    => $value,
			default               => (array) self::parseBackedEnumValue( $value )
		};
	}
}
