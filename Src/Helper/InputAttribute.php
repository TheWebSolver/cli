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
use TheWebSolver\Codegarage\Cli\Enum\InputVariant;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\InputDefinition;
use TheWebSolver\Codegarage\Cli\Data\Positional as Pos;
use Symfony\Component\Console\Completion\CompletionInput;
use TheWebSolver\Codegarage\Cli\Data\Associative as Assoc;

class InputAttribute {
	final public const EXTRACT_AND_REPLACE = 1;
	final public const EXTRACT_AND_UPDATE  = 2;

	final public const IMMUTABLE_INPUT_PROPERTIES = array( 'name', 'mode' );

	/** @var self::EXTRACT_AND* */
	private int $flag = self::EXTRACT_AND_REPLACE;

	/** @var array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag>> */
	private array $collect;

	/** @var array<class-string<Console>,array<class-string<Pos|Assoc|Flag>,array<string,array<int|string>>>> */
	private array $source;

	/** @var ReflectionClass<Console> */
	private ReflectionClass $target;

	/** @var class-string<Console> */
	private string $currentConsoleClass;

	/** @var array<class-string<Pos|Assoc|Flag>> */
	private array $inputClassNames;

	/** @var array{0:Pos|Assoc|Flag,1:int|string} */
	private array $inputAndProperty;

	/** @var mixed[] */
	private array $currentArguments;

	/** @var array<class-string<Pos|Assoc|Flag>,array<string,array<array-key,mixed>>> */
	private array $update;

	/** @var array<string,array<string|int>|(Closure(CompletionInput): list<string|Suggestion>)> */
	private array $suggestions;

	/** @param class-string<Console>|ReflectionClass<Console> $target */
	public function __construct( string|ReflectionClass $target ) {
		$this->target              = is_string( $target ) ? new ReflectionClass( $target ) : $target;
		$this->currentConsoleClass = $this->target->name;
	}

	/** @return array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag>> */
	public function getCollection(): array {
		return $this->collect ?? array();
	}

	/** @return array<class-string<Console>,array<class-string<Pos|Assoc|Flag>,array<string,array<int|string>>>> */
	public function getSource(): array {
		return $this->source ?? array();
	}

	/** @return array<string,array<string|int>|(Closure(CompletionInput): list<string|Suggestion>)> */
	public function getSuggestions(): array {
		return $this->suggestions ?? array();
	}

	/** @param class-string<Console>|ReflectionClass<Console> $target */
	public static function from( string|ReflectionClass $target ): self {
		return new self( $target );
	}

	/**
	 * Extracts inputs from the given `InputVariant`.
	 *
	 * If none of the `InputVariant` given, then all `InputVariant` types will be extracted.
	 *
	 * @param self::EXTRACT_AND* $mode
	 */
	public function do( int $mode, InputVariant ...$inputs ): self {
		$this->flag = $mode;

		return $this->extractInputVariants( ...( $inputs ?: InputVariant::cases() ) );
	}

	/** @return array<class-string<Pos|Assoc|Flag>,array<string,InputArgument|InputOption>> */
	public function toInput( ?InputDefinition $definition = null ): array {
		$collection = $this->getCollection();

		array_walk( $collection, self::walkCollectionToSymfonyInputs( ... ), $definition );

		/** @var array<class-string<Pos|Assoc|Flag>,array<string,InputArgument|InputOption>> */
		return $collection;
	}

	/** @return array<Pos|Assoc|Flag> */
	public function toFlattenedArray(): array {
		return array_reduce( $this->collect, $this->reduceToSingleArray( ... ), array() );
	}

	private function extractInputVariants( InputVariant ...$variants ): self {
		$this->inputClassNames = array_map( static fn( InputVariant $v ) => $v->getClassName(), $variants );

		return $this->performExtraction();
	}

	private function performExtraction(): self {
		$this->extractFrom( $this->target );

		if ( ! $parent = $this->targetParentClass() ) {
			return $this;
		}

		while ( $parent ) {
			$this->currentConsoleClass = $parent->getName();

			if ( $this->shouldUpdate() ) {
				$this->extractAndUpdateFrom( $parent );
			} else {
				$this->extractFrom( $parent );
			}

			$parent = $parent->getParentClass();
		}

		if ( $this->shouldUpdate() ) {
			$this->walkCollectionWithUpdatedInputProperties();
		}

		$this->reset();

		return $this;
	}

	/** @param ReflectionAttribute<Pos|Assoc|Flag> $attribute */
	private function prepareExtractionFrom( ReflectionAttribute $attribute ): Pos|Assoc|Flag {
		$input                  = $attribute->newInstance();
		$this->currentArguments = $attribute->getArguments();
		$this->inputAndProperty = array( $input, '' );

		return $input;
	}

	/** @param ReflectionClass<Console> $reflection */
	private function extractFrom( ReflectionClass $reflection ): void {
		foreach ( $reflection->getAttributes() as $attribute ) {
			if ( ! $this->isInputVariant( $attribute ) ) {
				continue;
			}

			$input = $this->prepareExtractionFrom( $attribute );
			$name  = $input->name;

			if ( $this->currentInputInCollectionQueue() ) {
				continue;
			}

			$this->pushCurrentInputToCollectionQueue();

			if ( $this->shouldUpdate() ) {
				$propertiesWithNamedArguments           = array( ...compact( 'name' ), ...$this->onlyNamedArguments() );
				$this->update[ $input::class ][ $name ] = $propertiesWithNamedArguments;
			}
		}
	}

	/** @param ReflectionClass<Console> $reflection */
	private function extractAndUpdateFrom( ReflectionClass $reflection ): void {
		foreach ( $reflection->getAttributes() as $attribute ) {
			if ( ! $this->isInputVariant( $attribute ) ) {
				continue;
			}

			$this->prepareExtractionFrom( $attribute );

			if ( ! $this->currentInputInCollectionQueue() ) {
				$this->pushCurrentInputToCollectionQueue();

				continue;
			}

			$this->pushCurrentInputPropertiesToUpdateQueue();
		}
	}

	private function walkCollectionWithUpdatedInputProperties(): void {
		if ( empty( $this->update ) ) {
			return;
		}

		foreach ( $this->update as $attributeName => $updatesInQueue ) {
			foreach ( $updatesInQueue as $inputName => $updatedProperties ) {
				if ( ! $input = $this->currentInputInCollectionQueue( $attributeName, $inputName ) ) {
					continue;
				}

				$this->collect[ $attributeName ][ $inputName ] = $input->with( $updatedProperties );

				$this->collectSuggestedValuesFrom( $input );
			}
		}
	}

	private function pushCurrentInputToCollectionQueue(): void {
		$args = $this->shouldUpdate() ? $this->onlyNamedArguments() : $this->currentArguments;

		[$input]                                        = $this->inputAndProperty;
		$this->collect[ $input::class ][ $input->name ] = $input;

		$this->collectSuggestedValuesFrom( $input );

		$this->source[ $this->currentConsoleClass ][ $input::class ][ $input->name ] = array_keys( $args );
	}

	private function pushCurrentInputPropertiesToUpdateQueue(): void {
		foreach ( get_object_vars( $this->inputAndProperty[0] ) as $this->inputAndProperty[1] => $value ) {
			if ( $this->updateQueueContainsCurrentProperty() ) {
				continue;
			}

			if ( $this->isCurrentPropertyNamedArgument() ) {
				$this->pushCurrentPropertyValueToUpdateQueue( $value );
			}
		}
	}

	private function pushCurrentPropertyValueToUpdateQueue( mixed $value ): void {
		[$input, $property]           = $this->inputAndProperty;
		[$hasValue, $defaultProperty] = $this->getDefaultPropertyValueAssigned();
		$value                        = $hasValue ? $defaultProperty : $value;

		$this->update[ $input::class ][ $input->name ][ $property ]                    = $value;
		$this->source[ $this->currentConsoleClass ][ $input::class ][ $input->name ][] = $property;
	}

	private function reset(): void {
		$this->currentConsoleClass = $this->target->name;
		$this->currentArguments    = array();
		$this->inputAndProperty    = array();
		$this->inputClassNames     = array();
		$this->update              = array();
	}

	/**
	 * @param ReflectionAttribute<T> $reflection
	 * @template T of object
	 * @phpstan-assert-if-true ReflectionAttribute<Pos|Assoc|Flag> $reflection
	 */
	private function isInputVariant( ReflectionAttribute $reflection ): bool {
		return in_array( $reflection->getName(), $this->inputClassNames, true );
	}

	private function currentInputInCollectionQueue(): Pos|Assoc|Flag|null {
		[$input]       = $this->inputAndProperty;
		$attributeName = $input::class;
		$inputName     = $input->name;

		/** @see self::walkCollectionWithUpdatedInputProperties() */
		if ( func_num_args() === 2 ) {
			[$attributeName, $inputName] = func_get_args();
		}

		return $this->collect[ $attributeName ][ $inputName ] ?? null;
	}

	private function updateQueueContainsCurrentProperty(): bool {
		[$input, $property] = $this->inputAndProperty;
		$propertiesInQueue  = $this->update[ $input::class ][ $input->name ] ?? array();

		return $propertiesInQueue && array_key_exists( $property, $propertiesInQueue );
	}

	private function isCurrentPropertyNamedArgument(): bool {
		[, $property] = $this->inputAndProperty;

		return $this->isCollectable( $property ) && array_key_exists( $property, $this->currentArguments );
	}

	private function shouldUpdate(): bool {
		return self::EXTRACT_AND_UPDATE === $this->flag;
	}

	/** @phpstan-assert-if-true =string $property */
	private function isCollectable( string|int $property ): bool {
		return ! is_int( $property ) && ! in_array( $property, self::IMMUTABLE_INPUT_PROPERTIES, true );
	}

	/** @return ReflectionClass<Console> */
	private function targetParentClass(): ?ReflectionClass {
		return ( $p = $this->target->getParentClass() ) && Console::class !== $p->getName() ? $p : null;
	}

	/** @return array<string,mixed> */
	private function onlyNamedArguments(): array {
		return array_filter( $this->currentArguments, $this->isCollectable( ... ), mode: ARRAY_FILTER_USE_KEY );
	}


	/** @return array{0:bool,1:null|string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{})} */
	private function getDefaultPropertyValueAssigned(): array {
		[$input, $property] = $this->inputAndProperty;

		return 'default' === $property && ! $input instanceof Flag
			? array( true, $input->getUserDefault() )
			: array( false, null );
	}

	private function collectSuggestedValuesFrom( Pos|Assoc|Flag $input ): void {
		if ( ! $input instanceof Flag && ( $value = $input->suggestedValues ) ) {
			$this->suggestions[ $input->name ] = $value;
		}
	}

	/**
	 * @param array<Pos|Assoc|Flag>        $carry
	 * @param array<string,Pos|Assoc|Flag> $inputs
	 * @return array<Pos|Assoc|Flag>
	 */
	private function reduceToSingleArray( array $carry, array $inputs ): array {
		return array( ...$carry, ...array_values( $inputs ) );
	}

	/**
	 * @param array<string,Pos|Assoc|Flag> $inputs
	 * @param class-string<Pos|Assoc|Flag> $targetClass
	 * @param-out array<string,InputArgument|InputOption> $inputs
	 */
	private static function walkCollectionToSymfonyInputs(
		array &$inputs,
		string $targetClass,
		?InputDefinition $definition
	): void {
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
