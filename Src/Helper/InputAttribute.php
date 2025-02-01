<?php
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
	private int $mode = self::INFER_AND_REPLACE;

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
	| These properties only exist within infer lifecycle. They are immediately purged
	| and cleared from the memory to make object less heavy when its reference still
	| exist on some another dependant class (eg: by default, on command classes).
	|
	| ----------------------------------------------------------------------------------
	| @see self::do()
	| @see self::purge()
	| ----------------------------------------------------------------------------------
	*/

	/**
	 * @var array{
	 *   ref:   ReflectionClass<Console>,
	 *   input: Pos|Assoc|Flag,
	 *   prop:  int|string,args:mixed[],
	 *   names: string[],
	 *   args:  mixed[]
	 * }
	 */
	private array $current;
	/** @var array<class-string<Pos|Assoc|Flag>> */
	private array $inputClassNames;
	/** @var array<class-string<Pos|Assoc|Flag>,array<string,array<array-key,mixed>>> */
	private array $update;

	/** @param class-string<Console>|ReflectionClass<Console> $target */
	public function __construct( string|ReflectionClass $target ) {
		$this->target         = is_string( $target ) ? new ReflectionClass( $target ) : $target;
		$this->current['ref'] = $this->target;
	}

	/**
	 * @param self::INFER_AND* $mode
	 * @return array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag>>
	 */
	public function __invoke( int $mode = self::INFER_AND_UPDATE, InputVariant ...$inputs ): array {
		return $this->register( $mode, ...$inputs )->parse()->getCollection();
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

	/** @return bool true if attributes not parsed, false otherwise. */
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
	public function getSuggestion(): array {
		return $this->suggestion ?? array();
	}

	/**
	 * @param string                    $name          The input name.
	 * @param ?class-string<TAttribute> $attributeName The attribute classname.
	 * @return ($attributeName is null ? Pos|Assoc|Flag|null : TAttribute|null)
	 * @template TAttribute of Pos|Assoc|Flag
	 */
	public function getInputBy( string $name, ?string $attributeName = null ): Pos|Assoc|Flag|null {
		if ( ! $c = $this->getCollection() ) {
			return null;
		}

		return $attributeName
			? ( $c[ $attributeName ][ $name ] ?? null )
			: ( $c[ Assoc::class ][ $name ] ?? $c[ Flag::class ][ $name ] ?? $c[ Pos::class ][ $name ] ?? null );
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
	public function register( int $mode = self::INFER_AND_UPDATE, InputVariant ...$inputs ): self {
		if ( ! $this->isValid() ) {
			return $this;
		}

		$this->mode = $mode;

		foreach ( $inputs ?: InputVariant::cases() as $variant ) {
			$this->inputClassNames[] = $variant->getClassName();
		}

		return $this;
	}

	public function parse(): self {
		return $this->isValid() ? $this->infer()->purge() : $this;
	}

	/**
	 * @param ?self::INFER_AND* $mode Defaults to whatever mode was registered with: `$this->register()`.
	 * @return bool True of input's user provided arguments are purged, false otherwise.
	 */
	public function add( Pos|Assoc|Flag $input, ?int $mode = null ): bool {
		$previous      = $this->registerCurrent( $input );
		$isInputPurged = $this->registerCurrentInput( ignoreIfExists: self::INFER_AND_REPLACE === $mode );

		$this->inUpdateMode() && $this->walkCollectionWithUpdatedInputProperties();
		$this->reset( current: $previous );

		return $isInputPurged;
	}

	/** @return array<class-string<Pos|Assoc|Flag>,array<string,InputArgument|InputOption>> */
	public function toSymfonyInput( ?InputDefinition $definition = null ): array {
		$collection = $this->getCollection();

		array_walk( $collection, self::toSymfonyInputs( ... ), $definition );

		/** @var array<class-string<Pos|Assoc|Flag>,array<string,InputArgument|InputOption>> */
		return $collection;
	}

	/** @return array<Pos|Assoc|Flag> */
	public function toFlattenedArray(): array {
		return array_reduce( $this->collection, self::toSingleArray( ... ), array() );
	}

	private function inUpdateMode(): bool {
		return self::INFER_AND_UPDATE === $this->mode;
	}

	private function inCollectionStack(): Pos|Assoc|Flag|null {
		$input         = $this->current['input'];
		$attributeName = $input::class;
		$inputName     = $input->name;

		/** @see self::walkCollectionWithUpdatedInputProperties() */
		if ( func_num_args() === 2 ) {
			[$attributeName, $inputName] = func_get_args();
		}

		return $this->collection[ $attributeName ][ $inputName ] ?? null;
	}

	/** @phpstan-assert !Flag $attribute */
	private function suggestibleInput( Pos|Assoc|Flag $attribute ): Pos|Assoc|null {
		return $attribute instanceof Flag ? null : $attribute;
	}

	private function toSuggestionStack( Pos|Assoc|Flag $input ): void {
		( $value = $this->suggestibleInput( $input )?->suggestedValues )
			&& ( $this->suggestion[ $input->name ] = $value );
	}

	private function pushCurrentInputToCollectionStack(): bool {
		['input' => $input, 'ref' => $target, 'args' => $namedArguments] = $this->current;

		if ( $this->inUpdateMode() ) {
			$this->update[ $input::class ][ $input->name ] = $namedArguments;
		}

		$this->collection[ $input::class ][ $input->name ]              = $input;
		$this->source[ $target->name ][ $input::class ][ $input->name ] = array_keys( $namedArguments );

		$this->toSuggestionStack( $input );

		return true;
	}

	private function toCollectionStack( bool $ignoreIfExists = false ): bool {
		return ( $ignoreIfExists || ! $this->inCollectionStack() ) && $this->pushCurrentInputToCollectionStack();
	}

	/**
	 * @return array{
	 *   0:bool,
	 *   1:null|string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{})
	 * }
	 */
	private function getCurrentInputDefaultPropertyValue(): array {
		return 'default' === $this->current['prop'] && ! ( $input = $this->current['input'] ) instanceof Flag
			? array( true, $input->getUserDefault() )
			: array( false, null );
	}

	private function currentPropertyInUpdateStack(): bool {
		['input' => $input, 'prop' => $prop] = $this->current;
		$propertiesInUpdateStack             = $this->update[ $input::class ][ $input->name ] ?? null;

		return $propertiesInUpdateStack && array_key_exists( $prop, $propertiesInUpdateStack );
	}

	private function currentPropertyValueToUpdateStack( mixed $value ): void {
		[$isDefaultProperty, $propertyValue] = $this->getCurrentInputDefaultPropertyValue();
		$value                               = $isDefaultProperty ? $propertyValue : $value;
		['input' => $input, 'prop' => $prop] = $this->current;

		$this->update[ $input::class ][ $input->name ][ $prop ]                         = $value;
		$this->source[ $this->current['ref']->name ][ $input::class ][ $input->name ][] = $prop;
	}

	private function toUpdateStack(): bool {
		if ( ! $this->inUpdateMode() ) {
			return false;
		}

		foreach ( $this->current['args'] as $this->current['prop'] => $value ) {
			$this->currentPropertyInUpdateStack() || $this->currentPropertyValueToUpdateStack( $value );
		}

		return true;
	}

	/** @param ReflectionClass<Console> $target */
	private function withCurrentTarget( ReflectionClass $target = null ): self {
		$this->current['ref'] = $target ?? $this->target;
		$this->hierarchy[]    = $this->current['ref']->name;

		return $this;
	}

	/**
	 * @param ReflectionAttribute<T> $reflection
	 * @template T of object
	 * @phpstan-assert-if-true ReflectionAttribute<Pos|Assoc|Flag> $reflection
	 */
	private function isInputVariant( ReflectionAttribute $reflection ): bool {
		return in_array( $reflection->getName(), $this->inputClassNames, true );
	}

	/**
	 * @return ?array{
	 *   ref:   ReflectionClass<Console>,
	 *   input: Pos|Assoc|Flag,
	 *   prop:  int|string,args:mixed[],
	 *   names: string[],
	 *   args:  mixed[]
	 * }
	 */
	private function registerCurrent( Pos|Assoc|Flag $input ): ?array {
		$previous = $this->current ?? null;
		$names    = array_keys( (array) $input->__debugInfo() );
		$prop     = '';
		$args     = $input->getPure();
		$ref      = $previous['ref'] ?? $this->target;

		// Pragmatically excluded "name" property.
		unset( $args['name'] );

		$this->current = compact( 'input', 'prop', 'args', 'names', 'ref' );

		return $previous;
	}

	private function registerCurrentInput( bool $ignoreIfExists = false ): bool {
		$this->toCollectionStack( $ignoreIfExists ) || $this->toUpdateStack();

		return $this->current['input']->purgePure();
	}

	/** @param ReflectionAttribute<object> $attribute */
	private function ensureInput( ReflectionAttribute $attribute ): bool {
		return $this->isInputVariant( $attribute )
			&& ! ! $this->registerCurrent( $attribute->newInstance() );
	}

	/** @return ReflectionClass<Console> */
	private function useAttributes(): ReflectionClass {
		foreach ( ( $target = $this->current['ref'] )->getAttributes() as $attribute ) {
			$this->ensureInput( $attribute ) && $this->registerCurrentInput( ignoreIfExists: false );
		}

		return $target;
	}

	private function isCurrentTargetLastParent(): bool {
		return ( $parent = $this->current['ref']->getParentClass() ) && $this->getBaseClass() === $parent->name;
	}

	/**
	 * @param mixed[]                      $props
	 * @param class-string<Pos|Assoc|Flag> $attrName
	 */
	private function fromUpdateToCollectionStack( array $props, string $name, string $attrName ): void {
		if ( ! $input = $this->inCollectionStack( $attrName, $name ) ) {
			return;
		}

		// @phpstan-ignore-next-line Properties are always valid as they come from attribute itself.
		$this->collection[ $attrName ][ $name ] = $input->with( $props );

		$this->toSuggestionStack( $input );
	}

	private function walkCollectionWithUpdatedInputProperties(): void {
		foreach ( $this->update ?? array() as $attributeName => $updates ) {
			array_walk( $updates, $this->fromUpdateToCollectionStack( ... ), $attributeName );
		}
	}

	private function infer(): self {
		$parent = $this->withCurrentTarget()->useAttributes()->getParentClass();

		while ( $parent ) {
			if ( $this->isCurrentTargetLastParent() ) {
				break;
			}

			$parent = $this->withCurrentTarget( $parent )->useAttributes()->getParentClass();
		}

		$this->inUpdateMode() && $this->walkCollectionWithUpdatedInputProperties();

		return $this;
	}

	/**
	 * @param null|array{
	 *   ref:   ReflectionClass<Console>,
	 *   input: Pos|Assoc|Flag,
	 *   prop:  int|string,args:mixed[],
	 *   names: string[],
	 *   args:  mixed[]
	 * } $current
	 */
	private function reset( ?array $current ): void {
		$current && ( $this->current = $current );
	}

	private function purge(): self {
		$this->lastTarget = $this->current['ref']->name;

		unset( $this->inputClassNames, $this->current, $this->update );

		return $this;
	}

	/**
	 * @param array<Pos|Assoc|Flag>        $carry
	 * @param array<string,Pos|Assoc|Flag> $inputs
	 * @return array<Pos|Assoc|Flag>
	 */
	private static function toSingleArray( array $carry, array $inputs ): array {
		return array( ...$carry, ...array_values( $inputs ) );
	}

	/**
	 * @param array<string,Pos|Assoc|Flag> $inputs
	 * @param class-string<Pos|Assoc|Flag> $key
	 * @param-out array<string,InputArgument|InputOption> $inputs
	 */
	private static function toSymfonyInputs( array &$inputs, string $key, ?InputDefinition $definition ): void {
		$inputs = array_map(
			callback: static function ( Pos|Assoc|Flag $attribute ) use ( $definition ) {
				if ( ( $input = $attribute->input() ) instanceof InputArgument ) {
					$definition?->addArgument( $input );
				} else {
					$definition?->addOption( $input );
				}

				return $input;
			},
			array: $inputs
		);
	}
}
