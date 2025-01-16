<?php // phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use ReflectionClass;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use TheWebSolver\Codegarage\Cli\Data\Positional as Pos;
use TheWebSolver\Codegarage\Cli\Data\Associative as Assoc;

class InputExtractor {
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

	/** @var array{0:Pos|Assoc|Flag,1:int|string} */
	private array $inputAndProperty;

	/** @var mixed[] */
	private array $currentArguments;

	/** @var array<class-string<Pos|Assoc|Flag>,array<string,array<array-key,mixed>>> */
	private array $update;

	/** @param class-string<Console>|ReflectionClass<Console> $target */
	public function __construct( string|ReflectionClass $target ) {
		$this->target              = $target instanceof ReflectionClass ? $target : new ReflectionClass( $target );
		$this->currentConsoleClass = $this->target->name;
	}

	/** @return array<string,array<string,Pos|Assoc|Flag>> */
	public function getCollection(): array {
		return $this->collect;
	}

	/** @return array<class-string<Console>,array<class-string<Pos|Assoc|Flag>,array<string,array<int|string>>>> */
	public function getUpdateSource(): array {
		return $this->source;
	}

	/** @param class-string<Console>|ReflectionClass<Console> $targetClass */
	public static function when( string|ReflectionClass $targetClass ): self {
		return new self( $targetClass );
	}

	/** @param self::EXTRACT_AND* $perform */
	public function needsTo( int $perform ): self {
		$this->flag = $perform;

		return $this;
	}

	public function extractAssociative(): self {
		$this->extractFrom( $this->target, Assoc::class );

		if ( ! $parent = $this->targetParentClass() ) {
			return $this;
		}

		while ( $parent ) {
			$this->currentConsoleClass = $parent->getName();

			if ( $this->shouldUpdate() ) {
				$this->extractAndUpdateFrom( $parent, Assoc::class );
			} else {
				$this->extractFrom( $parent, Assoc::class );
			}

			$parent = $parent->getParentClass();
		}

		if ( $this->shouldUpdate() ) {
			$this->updateCollectionOf( Assoc::class );
		}

		$this->reset();

		return $this;
	}

	/**
	 * @param ReflectionClass<Console>     $reflection
	 * @param class-string<Pos|Assoc|Flag> $attributeName
	 */
	private function extractFrom( ReflectionClass $reflection, string $attributeName ): void {
		foreach ( $reflection->getAttributes( $attributeName ) as $attribute ) {
			$input                  = $attribute->newInstance();
			$this->inputAndProperty = array( $input, '' );
			$this->currentArguments = $attribute->getArguments();

			if ( $this->currentInputInCollectionQueue() ) {
				continue;
			}

			$this->pushCurrentInputToCollectionQueue();

			if ( $this->shouldUpdate() ) {
				$this->update[ $input::class ][ $input->name ] = array(
					'name' => $input->name,
					...$this->onlyNamedArguments(),
				);
			}
		}
	}

	/**
	 * @param ReflectionClass<Console>     $reflection
	 * @param class-string<Pos|Assoc|Flag> $attributeName
	 */
	private function extractAndUpdateFrom( ReflectionClass $reflection, string $attributeName ): void {
		foreach ( $reflection->getAttributes( $attributeName ) as $attribute ) {
			$input                  = $attribute->newInstance();
			$this->currentArguments = $attribute->getArguments();
			$this->inputAndProperty = array( $input, '' );

			if ( ! $this->currentInputInCollectionQueue() ) {
				$this->pushCurrentInputToCollectionQueue();

				continue;
			}

			$this->pushCurrentInputPropertiesToUpdateQueue();
		}
	}

	/** @param class-string<Pos|Assoc|Flag> $attributeName */
	private function updateCollectionOf( string $attributeName ): void {
		if ( ! $updatesInQueue = ( $this->update[ $attributeName ] ?? false ) ) {
			return;
		}

		foreach ( $updatesInQueue as $inputName => $updates ) {
			if ( $input = $this->currentInputInCollectionQueue( $inputName ) ) {
				$this->collect[ $attributeName ][ $inputName ] = $input->with( $updates );
			}
		}
	}

	private function pushCurrentInputToCollectionQueue(): void {
		$args = $this->shouldUpdate() ? $this->onlyNamedArguments() : $this->currentArguments;

		[$input]                                        = $this->inputAndProperty;
		$this->collect[ $input::class ][ $input->name ] = $input;

		$this->source[ $this->currentConsoleClass ][ $input::class ][ $input->name ] = array_keys( $args );
	}

	private function pushCurrentInputPropertiesToUpdateQueue(): void {
		foreach ( get_object_vars( $this->inputAndProperty[0] ) as $this->inputAndProperty[1] => $value ) {
			if ( ! $this->currentPropertyIsInUpdateQueue() && $this->currentPropertyIsNamedArgument() ) {
				$this->pushCurrentPropertyValueToUpdateQueue( $value );
			}
		}
	}

	private function pushCurrentPropertyValueToUpdateQueue( mixed $value ): void {
		[$input, $property] = $this->inputAndProperty;
		$defaultPropValue   = $this->defaultPropertyValueAssigned();

		$this->update[ $input::class ][ $input->name ][ $property ] = $defaultPropValue ?? $value;

		$this->source[ $this->currentConsoleClass ][ $input::class ][ $input->name ][] = $property;
	}

	private function reset(): void {
		$this->currentArguments    = array();
		$this->inputAndProperty    = array();
		$this->update              = array();
		$this->currentConsoleClass = $this->target->name;
	}

	private function currentInputInCollectionQueue(): Pos|Assoc|Flag|null {
		[$input]   = $this->inputAndProperty;
		$inputName = func_num_args() === 1 ? func_get_arg( 0 ) : $input->name;

		return $this->collect[ $input::class ][ $inputName ] ?? null;
	}

	private function currentPropertyIsInUpdateQueue(): bool {
		[$input, $property] = $this->inputAndProperty;

		return isset( $this->update[ $input::class ][ $input->name ][ $property ] );
	}

	private function currentPropertyIsNamedArgument(): bool {
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

	private function defaultPropertyValueAssigned(): mixed {
		[$input, $property] = $this->inputAndProperty;

		return 'default' === $property && method_exists( $input, 'getUserDefault' ) ? $input->getUserDefault() : null;
	}
}
