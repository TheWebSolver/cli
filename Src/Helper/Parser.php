<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use Closure;
use UnitEnum;
use BackedEnum;
use ReflectionClass;
use ReflectionMethod;
use ReflectionAttribute;
use ReflectionException;
use ReflectionParameter;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;

class Parser {
	/** @return ($name is class-string<BackedEnum> ? array<($caseAsIndex is true ? string : int),string|int> : string)*/
	public static function parseBackedEnumValue( string $name, bool $caseAsIndex = false ): string|array {
		if ( is_a( $name, BackedEnum::class, allow_string: true ) ) {
			return array_column( $name::cases(), 'value', index_key: $caseAsIndex ? 'name' : null );
		} elseif ( is_a( $name, UnitEnum::class, allow_string: true ) ) {
			return array_column( $name::cases(), 'name', index_key: $caseAsIndex ? 'name' : null );
		}

		return $name;
	}

	/**
	 * @param class-string<BackedEnum>|array{}|callable(CompletionInput): list<string|Suggestion> $value
	 * @return array<string|int>|Closure(CompletionInput): list<string|Suggestion>
	 */
	public static function parseInputSuggestion( string|callable|array $value ): array|Closure {
		$isCallable = is_callable( $value );

		return match ( true ) {
			is_string( $value ) => $isCallable ? $value( ... ) : (array) self::parseBackedEnumValue( $value ),
			$isCallable         => $value( ... ),
			default             => $value
		};
	}

	/**
	 * @param class-string<TAttribute>                       $attributeName
	 * @param class-string<TTarget>|ReflectionClass<TTarget> $target
	 * @return ?array<ReflectionAttribute<TAttribute>>
	 * @template TAttribute of object
	 * @template TTarget of object
	 */
	public static function parseClassAttribute( string $attributeName, string|ReflectionClass $target ): ?array {
		$reflection = $target instanceof ReflectionClass ? $target : new ReflectionClass( $target );

		return empty( $attrs = $reflection->getAttributes( $attributeName ) ) ? null : $attrs;
	}

	/** @return null|array<string|int>|callable(CompletionInput): list<string|Suggestion> */
	public static function suggestedValuesFrom( InputArgument|InputOption $input ): array|callable|null {
		try {
			return ( new ReflectionClass( $input ) )->getProperty( 'suggestedValues' )->getValue( $input );
		} catch ( ReflectionException ) {
			return null;
		}
	}

	/** @return string[] The parameter names of target class method's accepted parameters. */
	public static function parseParamNamesOf( object|string $target, string $methodName ): array {
		return array_map(
			callback: static fn( ReflectionParameter $param ): string => $param->name,
			array: ( new ReflectionMethod( $target, $methodName ) )->getParameters()
		);
	}

	/**
	 * @param string[] $paramNames  Names parsed using `Parser::parseParamNamesOf()`.
	 * @param mixed[]  $paramValues Values of `func_get_args()`.
	 * @return array<string,mixed>
	 */
	public static function combineParamNamesWithUserArgs( array $paramNames, array $paramValues ): array {
		return array_combine( keys: array_intersect_key( $paramNames, $paramValues ), values: $paramValues );
	}
}
