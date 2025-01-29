<?php // phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use Closure;
use ReflectionClass;
use ReflectionAttribute;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use TheWebSolver\Codegarage\Cli\Enums\InputVariant;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\InputDefinition;
use TheWebSolver\Codegarage\Cli\Data\Positional as Pos;
use Symfony\Component\Console\Completion\CompletionInput;
use TheWebSolver\Codegarage\Cli\Data\Associative as Assoc;

class InputAttribute {
	/** Infers attributes recursively and overrides parent class attribute with same name. */
	final public const INFER_AND_REPLACE = 1;

	/**
	 * Infers attributes recursively and only updates parent class attribute's with same name. But,
	 * only attribute values passed as named argument will replace attribute values of parent class.
	 */
	final public const INFER_AND_UPDATE = 2;

	final public const IMMUTABLE_INPUT_PROPERTIES = array( 'name', 'mode' );

	/** @var self::INFER_AND* */
	private int $flag = self::INFER_AND_REPLACE;

	/** @var class-string<Console> */
	private string $baseClassName;
	/** @var ReflectionClass<Console> */
	private ReflectionClass $target;
	/** @var array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag>> */
	private array $collection;
	/** @var array<string,array<string|int>|(Closure(CompletionInput): list<string|Suggestion>)> */
	private array $suggestion;
	/** @var array<class-string<Console>,array<class-string<Pos|Assoc|Flag>,array<string,array<int|string>>>> */
	private array $source;

	/*
	| ----------------------------------------------------------------------------------
	| Flushable properties.
	| ----------------------------------------------------------------------------------
	|
	| These properties only exist within infer lifecycle. They are immediately flushed
	| and cleared from the memory to make object less heavy when its reference still
	| exist on some another dependant class (eg: by default, on command classes).
	|
	| ----------------------------------------------------------------------------------
	| @see self::do()
	| @see self::flush()
	| ----------------------------------------------------------------------------------
	*/

	/** @var mixed[] */
	private array $currentArguments;
	/** @var array{0:Pos|Assoc|Flag,1:int|string} */
	private array $inputAndProperty;
	/** @var array<class-string<Pos|Assoc|Flag>> */
	private array $inputClassNames;
	/** @var ReflectionClass<Console> */
	private ReflectionClass $currentTarget;
	/** @var array<class-string<Pos|Assoc|Flag>,array<string,array<array-key,mixed>>> */
	private array $update;

	/** @param class-string<Console>|ReflectionClass<Console> $target */
	public function __construct( string|ReflectionClass $target ) {
		$this->target        = is_string( $target ) ? new ReflectionClass( $target ) : $target;
		$this->currentTarget = $this->target;
	}

	/** @return class-string<Console> */
	public function getBaseClass(): ?string {
		return $this->baseClassName ?? Console::class;
	}

	/** @return ReflectionClass<Console> */
	public function getTargetReflection(): ReflectionClass {
		return $this->target;
	}

	/** @return array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag>> */
	public function getCollection(): array {
		return $this->collection ?? array();
	}

	/** @return array<class-string<Console>,array<class-string<Pos|Assoc|Flag>,array<string,array<int|string>>>> */
	public function getSource(): array {
		return $this->source ?? array();
	}

	/** @return array<string,array<string|int>|(Closure(CompletionInput): list<string|Suggestion>)> */
	public function getSuggestions(): array {
		return $this->suggestion ?? array();
	}

	/**
	 * @param string                    $name          The input name.
	 * @param ?class-string<TAttribute> $attributeName The attribute classname.
	 * @return ($attributeName is null ? Pos|Assoc|Flag|null : TAttribute|null)
	 * @template TAttribute of Pos|Assoc|Flag
	 */
	public function by( string $name, ?string $attributeName = null ): Pos|Assoc|Flag|null {
		if ( ! $inputs = $this->getCollection() ) {
			return null;
		}

		if ( $attributeName ) {
			return $inputs[ $attributeName ][ $name ] ?? null;
		}

		return $inputs[ Assoc::class ][ $name ]
			?? $inputs[ Flag::class ][ $name ]
			?? $inputs[ Pos::class ][ $name ]
			?? null;
	}

	/**
	 * Starts extraction from the topmost subclass of the Console class inheritance hierarchy.
	 *
	 * @param class-string<Console>|ReflectionClass<Console> $target
	 */
	public static function from( string|ReflectionClass $target ): self {
		return new self( $target );
	}

	/**
	 * Ends extraction at the lowermost subclass of the Console class inheritance hierarchy.
	 *
	 * @param class-string<Console> $baseClassName It (and its parent classes) won't be used for parsing.
	 */
	public function till( string $baseClassName = Console::class ): self {
		$this->baseClassName ??= $baseClassName;

		return $this;
	}

	/**
	 * Infers inputs from the given `InputVariant`.
	 *
	 * If none of the `InputVariant` given, then all `InputVariant` types will be inferred.
	 *
	 * @param self::INFER_AND* $mode
	 */
	public function do( int $mode, InputVariant ...$inputs ): self {
		$this->flag = $mode;

		foreach ( $inputs ?: InputVariant::cases() as $variant ) {
			$this->inputClassNames[] = $variant->getClassName();
		}

		return $this->infer()->flush();
	}

	/** @return array<class-string<Pos|Assoc|Flag>,array<string,InputArgument|InputOption>> */
	public function toInput( ?InputDefinition $definition = null ): array {
		$collection = $this->getCollection();

		array_walk( $collection, self::toSymfonyInputs( ... ), $definition );

		/** @var array<class-string<Pos|Assoc|Flag>,array<string,InputArgument|InputOption>> */
		return $collection;
	}

	/** @return array<Pos|Assoc|Flag> */
	public function toFlattenedArray(): array {
		return array_reduce( $this->collection, $this->toSingleArray( ... ), array() );
	}

	private function infer(): self {
		$this->inferFrom( $this->target );

		if ( ! $parent = $this->getTargetParent() ) {
			return $this;
		}

		while ( $parent ) {
			$this->currentTarget = $parent;

			if ( $this->shouldUpdate() ) {
				$this->inferAndUpdateFrom( $parent );
			} else {
				$this->inferFrom( $parent );
			}

			$parent = $parent->getParentClass();
		}

		if ( $this->shouldUpdate() ) {
			$this->walkCollectionWithUpdatedInputProperties();
		}

		return $this;
	}

	/** @param ReflectionAttribute<Pos|Assoc|Flag> $attribute */
	private function useCurrent( ReflectionAttribute $attribute ): Pos|Assoc|Flag {
		$input                  = $attribute->newInstance();
		$this->currentArguments = $attribute->getArguments();
		$this->inputAndProperty = array( $input, '' );

		return $input;
	}

	/** @param ReflectionClass<Console> $reflection */
	private function inferFrom( ReflectionClass $reflection ): void {
		foreach ( $reflection->getAttributes() as $attribute ) {
			if ( ! $this->isInputVariant( $attribute ) ) {
				continue;
			}

			$this->useCurrent( $attribute );

			if ( $this->currentInputInCollectionStack() ) {
				continue;
			}

			$this->pushCurrentInputToCollectionStack();
		}
	}

	/** @param ReflectionClass<Console> $reflection */
	private function inferAndUpdateFrom( ReflectionClass $reflection ): void {
		foreach ( $reflection->getAttributes() as $attribute ) {
			if ( ! $this->isInputVariant( $attribute ) ) {
				continue;
			}

			$this->useCurrent( $attribute );

			if ( ! $this->currentInputInCollectionStack() ) {
				$this->pushCurrentInputToCollectionStack();

				continue;
			}

			$this->updateWithCurrentInputProperties();
		}
	}

	private function walkCollectionWithUpdatedInputProperties(): void {
		if ( empty( $this->update ) ) {
			return;
		}

		foreach ( $this->update as $attributeName => $inputStack ) {
			foreach ( $inputStack as $name => $updatedProperties ) {
				if ( ! $input = $this->currentInputInCollectionStack( $attributeName, $name ) ) {
					continue;
				}

				$updatedProperties                           = array( ...compact( 'name' ), ...$updatedProperties );
				$this->collection[ $attributeName ][ $name ] = $input->with( $updatedProperties );

				$this->toSuggestedValuesCollection( $input );
			}
		}
	}

	private function pushCurrentInputToCollectionStack(): void {
		[$input] = $this->inputAndProperty;
		$args    = $this->shouldUpdate() ? $this->onlyNamedArguments() : $this->currentArguments;

		if ( $this->shouldUpdate() ) {
			$this->update[ $input::class ][ $input->name ] = $args;
		}

		[$shouldCollectCurrentInput, $propertyNames] = $this->getPropertyNamesFromCurrentArguments();

		if ( ! $shouldCollectCurrentInput ) {
			return;
		}

		$this->collection[ $input::class ][ $input->name ] = $input;
		$arguments = $propertyNames ?? array_keys( $args );

		// Omit sourcing input's "name" property by determining it's position.
		unset( $arguments[ $this->getPositionIn( $arguments, propertyName: 'name' ) ] );

		// And source whatever properties left.
		if ( ! empty( $arguments ) ) {
			$this->source[ $this->currentTarget->name ][ $input::class ][ $input->name ] = $arguments;
		}

		$this->toSuggestedValuesCollection( $input );
	}

	private function updateWithCurrentInputProperties(): void {
		foreach ( get_object_vars( $this->inputAndProperty[0] ) as $this->inputAndProperty[1] => $value ) {
			if ( $this->updateStackContainsCurrentProperty() ) {
				continue;
			}

			if ( $this->isCurrentPropertyNamedArgument() ) {
				$this->pushCurrentPropertyValueToUpdateStack( $value );
			}
		}
	}

	private function pushCurrentPropertyValueToUpdateStack( mixed $value ): void {
		[$isDefaultProperty, $propertyValue] = $this->getCurrentInputDefaultPropertyValue();
		[$input, $property]                  = $this->inputAndProperty;
		$value                               = $isDefaultProperty ? $propertyValue : $value;

		$this->update[ $input::class ][ $input->name ][ $property ]                    = $value;
		$this->source[ $this->currentTarget->name ][ $input::class ][ $input->name ][] = $property;
	}

	private function flush(): self {
		unset(
			$this->currentArguments,
			$this->inputAndProperty,
			$this->inputClassNames,
			$this->currentTarget,
			$this->update
		);

		return $this;
	}

	private function currentInputInCollectionStack(): Pos|Assoc|Flag|null {
		[$input]       = $this->inputAndProperty;
		$attributeName = $input::class;
		$inputName     = $input->name;

		/** @see self::walkCollectionWithUpdatedInputProperties() */
		if ( func_num_args() === 2 ) {
			[$attributeName, $inputName] = func_get_args();
		}

		return $this->collection[ $attributeName ][ $inputName ] ?? null;
	}

	/** @return ReflectionClass<Console> */
	private function getTargetParent(): ?ReflectionClass {
		return ( $p = $this->target->getParentClass() ) && $this->getBaseClass() !== $p->getName() ? $p : null;
	}

	/** @return array<string,mixed> */
	private function onlyNamedArguments(): array {
		return array_filter( $this->currentArguments, $this->isCollectable( ... ), mode: ARRAY_FILTER_USE_KEY );
	}

	/** @param string[] $arguments */
	private function getPositionIn( array $arguments, string $propertyName ): string|int|null {
		return false !== ( $index = array_search( $propertyName, $arguments, strict: true ) ) ? $index : null;
	}

	/**
	 * @return array{0:bool,1:?string[]} `0:` should collect current input, `1:` property names if indexed args.
	 */
	private function getPropertyNamesFromCurrentArguments(): array {
		$unnamedArgs   = $this->hasNamelessArguments();
		$collect       = ! ( $this->shouldUpdate() && $unnamedArgs ) || $this->isCurrentTargetLastParent();
		$needsName     = ( ! $this->shouldUpdate() && $unnamedArgs ) || $unnamedArgs;
		$propertyNames = $needsName && $collect ? $this->toPropertyNamesFromCurrentArgumentsIndex() : null;

		return array( $collect, $propertyNames );
	}

	/**
	 * @return array{
	 *   0:bool,
	 *   1:null|string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{})
	 * }
	 */
	private function getCurrentInputDefaultPropertyValue(): array {
		[$input, $property] = $this->inputAndProperty;

		return 'default' === $property && ! $input instanceof Flag
			? array( true, $input->getUserDefault() )
			: array( false, null );
	}

	/**
	 * @param ReflectionAttribute<T> $reflection
	 * @template T of object
	 * @phpstan-assert-if-true ReflectionAttribute<Pos|Assoc|Flag> $reflection
	 */
	private function isInputVariant( ReflectionAttribute $reflection ): bool {
		return in_array( $reflection->getName(), $this->inputClassNames, true );
	}

	private function updateStackContainsCurrentProperty(): bool {
		[$input, $property] = $this->inputAndProperty;
		$propertiesInStack  = $this->update[ $input::class ][ $input->name ] ?? array();

		return $propertiesInStack && array_key_exists( $property, $propertiesInStack );
	}

	private function shouldUpdate(): bool {
		return self::INFER_AND_UPDATE === $this->flag;
	}

	/** @phpstan-assert-if-true =string $property */
	private function isCollectable( string|int $property ): bool {
		return ! is_int( $property ) && ! in_array( $property, self::IMMUTABLE_INPUT_PROPERTIES, true );
	}

	private function isCurrentPropertyNamedArgument(): bool {
		[, $property] = $this->inputAndProperty;

		return $this->isCollectable( $property ) && array_key_exists( $property, $this->currentArguments );
	}

	private function isNamedArgument( string|int $propertyNameOrIndex ): bool {
		return 0 !== $propertyNameOrIndex /*ignore: "name" property*/ && ! is_int( $propertyNameOrIndex );
	}

	private function hasNamelessArguments(): bool {
		return empty(
			array_filter( $this->currentArguments, $this->isNamedArgument( ... ), ARRAY_FILTER_USE_KEY )
		);
	}

	private function isCurrentTargetLastParent(): bool {
		$currentTargetParent = $this->currentTarget->getParentClass();

		return ! $currentTargetParent || ( $currentTargetParent->name === $this->getBaseClass() );
	}

	/** @return string[] */
	private function toPropertyNamesFromCurrentArgumentsIndex(): array {
		$props = array();

		foreach ( get_object_vars( $this->inputAndProperty[0] ) as $prop => $value ) {
			if ( ! $this->isCollectable( $prop ) ) {
				continue;
			}

			if ( in_array( $value, $this->currentArguments, strict: true ) ) {
				$props[] = $prop;
			}
		}

		return $props;
	}

	/**
	 * @param array<Pos|Assoc|Flag>        $carry
	 * @param array<string,Pos|Assoc|Flag> $inputs
	 * @return array<Pos|Assoc|Flag>
	 */
	private function toSingleArray( array $carry, array $inputs ): array {
		return array( ...$carry, ...array_values( $inputs ) );
	}

	private function toSuggestedValuesCollection( Pos|Assoc|Flag $input ): void {
		if ( ! $input instanceof Flag && ( $value = $input->suggestedValues ) ) {
			$this->suggestion[ $input->name ] = $value;
		}
	}

	/**
	 * @param array<string,Pos|Assoc|Flag> $inputs
	 * @param class-string<Pos|Assoc|Flag> $key
	 * @param-out array<string,InputArgument|InputOption> $inputs
	 */
	private static function toSymfonyInputs( array &$inputs, string $key, ?InputDefinition $definition ): void {
		$inputs = array_map(
			static function ( Pos|Assoc|Flag $attribute ) use ( $definition ) {
				$input = $attribute->input();

				if ( $input instanceof InputArgument ) {
					$definition?->addArgument( $input );
				} else {
					$definition?->addOption( $input );
				}

				return $input;
			},
			$inputs
		);
	}
}
