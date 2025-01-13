<?php // phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use Closure;
use BackedEnum;
use ReflectionClass;
use ReflectionAttribute;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use Symfony\Component\Console\Input\InputOption;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;

class Parser {
	/** @return ($name is class-string<BackedEnum> ? array<($caseAsIndex is true ? string : int),string|int> : string)*/
	public static function parseBackedEnumValue( string $name, bool $caseAsIndex = false ): string|array {
		return is_a( $name, BackedEnum::class, allow_string: true )
			? array_column( $name::cases(), 'value', index_key: $caseAsIndex ? 'name' : null )
			: $name;
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
	public static function parseClassAttribute( // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
		string $attributeName,
		string|ReflectionClass $target
	): ?array {
		$reflection = $target instanceof ReflectionClass ? $target : new ReflectionClass( $target );

		return empty( $attrs = $reflection->getAttributes( $attributeName ) ) ? null : $attrs;
	}

	/**
	 * @param class-string<TAttribute>                       $attributeName
	 * @param class-string<TTarget>|ReflectionClass<TTarget> $target
	 * @return (
	 *   $toInput is true
	 *     ? ($attributeName is class-string<Positional> ? ?InputArgument[] : ?InputOption[])
	 *     : ?TAttribute[]
	 * )
	 * @template TAttribute of Positional|Associative|Flag
	 * @template TTarget of object
	 */
	public static function parseInputAttribute(
		string $attributeName,
		string|ReflectionClass $target,
		bool $toInput
	): ?array {
		if ( ! $attributes = self::parseClassAttribute( $attributeName, $target ) ) {
			return null;
		}

		return array_map(
			static fn( ReflectionAttribute $a ) => $toInput ? $a->newInstance()->input() : $a->newInstance(),
			$attributes
		);
	}
}
