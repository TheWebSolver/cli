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
	/** @var class-string<Console> */
	private string $lastTarget;
	/** @var array<class-string> */
	private array $hierarchy;

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
	private array $currentInput;
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

	/** @return array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag>> */
	public function __invoke( int $mode = self::INFER_AND_UPDATE, InputVariant ...$inputs ): array {
		return $this->do( $mode, ...$inputs )->getCollection();
	}

	public function __debugInfo() {
		return array(
			'status'    => ! $this->isValid(),
			'hierarchy' => $this->hierarchy ?? array(),
			'target'    => array(
				'from' => $this->getTargetReflection()->name,
				'till' => $this->lastTarget ?? false,
				'base' => $this->getBaseClass(),
			),
		);
	}

	public function isValid(): bool {
		return empty( $this->hierarchy );
	}

	/** @return class-string<Console> */
	public function getBaseClass(): string {
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
		if ( ! $collection = $this->getCollection() ) {
			return null;
		}

		if ( $attributeName ) {
			return $collection[ $attributeName ][ $name ] ?? null;
		}

		return $collection[ Assoc::class ][ $name ]
			?? $collection[ Flag::class ][ $name ]
			?? $collection[ Pos::class ][ $name ]
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
		if ( ! $this->isValid() ) {
			return $this;
		}

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

	/** @param ReflectionClass<Console> $target */
	private function withCurrentTarget( ReflectionClass $target = null ): self {
		$this->currentTarget = $target ?? $this->target;
		$this->hierarchy[]   = $this->currentTarget->name;

		return $this;
	}

	private function infer(): self {
		$this->withCurrentTarget()->parseAttributes();

		$parent = $this->target->getParentClass();

		while ( $parent ) {
			if ( $this->isCurrentTargetLastParent() ) {
				break;
			}

			$this->withCurrentTarget( $parent )->parseAttributes();

			$parent = $parent->getParentClass();
		}

		if ( $this->shouldUpdate() ) {
			$this->walkCollectionWithUpdatedInputProperties();
		}

		return $this;
	}

	/** @param ReflectionAttribute<object> $attribute */
	private function ensureInput( ReflectionAttribute $attribute ): bool {
		if ( $this->isInputVariant( $attribute ) ) {
			$input                  = $attribute->newInstance();
			$this->currentArguments = $attribute->getArguments();
			$this->currentInput     = array( $input, '' );
		}

		return ! empty( $this->currentInput );
	}

	private function parseAttributes(): void {
		foreach ( $this->currentTarget->getAttributes() as $attribute ) {
			$this->ensureInput( $attribute ) && ( $this->toCollectionStack() || $this->toUpdateStack() );
		}
	}

	private function walkCollectionWithUpdatedInputProperties(): void {
		foreach ( $this->update ?? array() as $attributeName => $updates ) {
			array_walk( $updates, $this->fromUpdateToCollectionStack( ... ), $attributeName );
		}
	}

	private function pushCurrentInputToCollectionStack(): bool {
		[$input] = $this->currentInput;
		$args    = $this->shouldUpdate() ? $this->onlyNamedArguments() : $this->currentArguments;

		if ( $this->shouldUpdate() ) {
			$this->update[ $input::class ][ $input->name ] = $args;
		}

		[$shouldCollectCurrentInput, $propertyNames] = $this->getPropertyNamesFromCurrentArguments();

		if ( ! $shouldCollectCurrentInput ) {
			return false;
		}

		$this->collection[ $input::class ][ $input->name ] = $input;
		$propertyNames                                   ??= array_keys( $args );

		// Omit sourcing input's "name" property by determining it's position.
		unset( $propertyNames[ $this->getPropertyPositionIn( $propertyNames, propertyName: 'name' ) ] );

		// And source whatever properties left.
		if ( ! empty( $propertyNames ) ) {
			$this->source[ $this->currentTarget->name ][ $input::class ][ $input->name ] = $propertyNames;
		}

		$this->toSuggestedValuesCollection( $input );

		return true;
	}

	private function toCollectionStack(): bool {
		return ! $this->currentInputInCollectionStack() && $this->pushCurrentInputToCollectionStack();
	}

	/**
	 * @param mixed[]                      $props
	 * @param class-string<Pos|Assoc|Flag> $attrName
	 */
	private function fromUpdateToCollectionStack( array $props, string $name, string $attrName ): void {
		if ( ! $input = $this->currentInputInCollectionStack( $attrName, $name ) ) {
			return;
		}

		$this->collection[ $attrName ][ $name ] = $input->with( array( ...compact( 'name' ), ...$props ) );

		$this->toSuggestedValuesCollection( $input );
	}

	private function toUpdateStack(): bool {
		if ( ! $this->shouldUpdate() ) {
			return false;
		}

		foreach ( get_object_vars( $this->currentInput[0] ) as $this->currentInput[1] => $value ) {
			$this->updateStackContainsCurrentProperty() ||
			( $this->isCurrentPropertyNamedArgument() && $this->currentPropertyValueToUpdateStack( $value ) );
		}

		return true;
	}

	private function currentPropertyValueToUpdateStack( mixed $value ): void {
		[$isDefaultProperty, $propertyValue] = $this->getCurrentInputDefaultPropertyValue();
		[$input, $property]                  = $this->currentInput;
		$value                               = $isDefaultProperty ? $propertyValue : $value;

		$this->update[ $input::class ][ $input->name ][ $property ]                    = $value;
		$this->source[ $this->currentTarget->name ][ $input::class ][ $input->name ][] = $property;
	}

	private function flush(): self {
		$this->lastTarget = $this->currentTarget->name;

		unset(
			$this->currentArguments,
			$this->currentInput,
			$this->inputClassNames,
			$this->currentTarget,
			$this->update
		);

		return $this;
	}

	private function currentInputInCollectionStack(): Pos|Assoc|Flag|null {
		[$input]       = $this->currentInput;
		$attributeName = $input::class;
		$inputName     = $input->name;

		/** @see self::walkCollectionWithUpdatedInputProperties() */
		if ( func_num_args() === 2 ) {
			[$attributeName, $inputName] = func_get_args();
		}

		return $this->collection[ $attributeName ][ $inputName ] ?? null;
	}

	/** @return array<string,mixed> */
	private function onlyNamedArguments(): array {
		return array_filter( $this->currentArguments, $this->isCollectable( ... ), mode: ARRAY_FILTER_USE_KEY );
	}

	/** @param string[] $names */
	private function getPropertyPositionIn( array $names, string $propertyName ): string|int|null {
		return false !== ( $index = array_search( $propertyName, $names, strict: true ) ) ? $index : null;
	}

	/** @return array{0:bool,1:?string[]} `0:` should collect current input, `1:` property names if indexed args. */
	private function getPropertyNamesFromCurrentArguments(): array {
		$unnamedArgs   = $this->hasNamelessArguments();
		$collect       = ! ( $this->shouldUpdate() && $unnamedArgs ) || $this->isCurrentTargetLastParent();
		$needsName     = ( ! $this->shouldUpdate() && $unnamedArgs ) || $unnamedArgs;
		$propertyNames = $needsName && $collect ? $this->toPropertyNamesFromCurrentArgumentsPosition() : null;

		return array( $collect, $propertyNames );
	}

	/**
	 * @return array{
	 *   0:bool,
	 *   1:null|string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{})
	 * }
	 */
	private function getCurrentInputDefaultPropertyValue(): array {
		[$input, $property] = $this->currentInput;

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
		[$input, $property] = $this->currentInput;
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
		[, $property] = $this->currentInput;

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
		return ( $parent = $this->currentTarget->getParentClass() ) && $this->getBaseClass() === $parent->name;
	}

	/** @return string[] */
	private function toPropertyNamesFromCurrentArgumentsPosition(): array {
		$props = array();

		foreach ( get_object_vars( $this->currentInput[0] ) as $prop => $value ) {
			$this->isCollectable( $prop )
				&& in_array( $value, $this->currentArguments, strict: true )
				&& ( $props[] = $prop );
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
