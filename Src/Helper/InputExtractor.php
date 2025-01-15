<?php // phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use ReflectionClass;
use ReflectionAttribute;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use TheWebSolver\Codegarage\Cli\Data\Positional as Pos;
use TheWebSolver\Codegarage\Cli\Data\Associative as Assoc;

class InputExtractor {
	final public const EXTRACT_AND_REPLACE = 1;
	final public const EXTRACT_AND_UPDATE  = 2;

	/** @var self::EXTRACT_AND* */
	private int $flag = self::EXTRACT_AND_REPLACE;

	/** @var array{0:Pos|Assoc|Flag,1:int|string} */
	private array $inputAndProp;

	/** @var array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag>> */
	private array $collect;

	/** @var array<class-string<Console>,array<class-string<Pos|Assoc|Flag>,array<string,string[]>>> */
	private array $source;

	/** @var ReflectionClass<Console> */
	private ReflectionClass $target;

	/** @var array<class-string<Pos|Assoc|Flag>,array<string,array<array-key,mixed>>> */
	private array $updates;

	/** @param class-string<Console>|ReflectionClass<Console> $target */
	public function __construct( string|ReflectionClass $target ) {
		$this->target = $target instanceof ReflectionClass ? $target : new ReflectionClass( $target );
	}

	/** @return array<string,array<string,Pos|Assoc|Flag>> */
	public function getCollection(): array {
		return $this->collect;
	}

	/** @return array<class-string<Console>,array<class-string<Pos|Assoc|Flag>,array<string,string[]>>> */
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
		$this->extractUsing( $this->target, Assoc::class );

		if ( ! $parent = $this->target->getParentClass() ) {
			return $this;
		}

		while ( $parent ) {
			if ( Console::class === $parent->getName() ) {
				break;
			}

			if ( ! $this->shouldUpdate() ) {
				$this->extractUsing( $parent, Assoc::class );
			} else {
				$this->extractAndAssignUsing( $parent, Assoc::class );
			}

			$parent = $parent->getParentClass();
		}

		if ( $this->shouldUpdate() ) {
			$this->applyUpdates();
		}

		return $this;
	}

	/**
	 * @param ReflectionClass<Console>     $reflection
	 * @param class-string<Pos|Assoc|Flag> $attributeName
	 */
	private function extractUsing( ReflectionClass $reflection, string $attributeName ): void {
		foreach ( $reflection->getAttributes( $attributeName ) as $attribute ) {
			$input = $attribute->newInstance();

			if ( $this->inputAlreadyCollectedUsing( $attributeName, $input->name ) ) {
				continue;
			}

			$this->collectInputUsing( $reflection->getName(), $args = $attribute->getArguments(), $input );

			if ( $this->shouldUpdate() ) {
				$this->updates[ $input::class ][ $input->name ] = $args;
			}
		}
	}

	/**
	 * @param ReflectionClass<Console>     $reflection
	 * @param class-string<Pos|Assoc|Flag> $attributeName
	 */
	private function extractAndAssignUsing( ReflectionClass $reflection, string $attributeName ): void {
		foreach ( $reflection->getAttributes( $attributeName ) as $attribute ) {
			$input = $attribute->newInstance();

			if ( ! $this->inputAlreadyCollectedUsing( $attributeName, $input->name ) ) {
				$this->collectInputUsing( $reflection->getName(), $attribute->getArguments(), $input );

				continue;
			}

			$this->updateInputAlreadyCollectedUsingCurrentClass( $attribute, $reflection->getName(), $input );
		}
	}

	/**
	 * @param class-string<Console> $reflectionClass
	 * @param mixed[]               $args
	 */
	private function collectInputUsing( string $reflectionClass, array $args, Pos|Assoc|Flag $input ): void {
		$sourced = array_filter( array_keys( $args ), $this->isCollectable( ... ) );

		$this->collect[ $input::class ][ $input->name ]                    = $input;
		$this->source[ $reflectionClass ][ $input::class ][ $input->name ] = $sourced;
	}

	private function applyUpdates(): void {
		foreach ( $this->updates as $attributeName => $inputs ) {
			foreach ( $inputs as $inputName => $updates ) {
				if ( $input = $this->inputAlreadyCollectedUsing( $attributeName, $inputName ) ) {
					$this->collect[ $attributeName ][ $inputName ] = $input->with( $updates );
				}
			}
		}
	}

	/**
	 * @param ReflectionAttribute<Pos|Assoc|Flag> $attribute
	 * @param class-string<Console>               $reflectionClass
	 * @param Pos|Assoc|Flag                      $input
	 */
	private function updateInputAlreadyCollectedUsingCurrentClass(
		ReflectionAttribute $attribute,
		string $reflectionClass,
		Pos|Assoc|Flag $input
	): void {
		foreach ( get_object_vars( $input ) as $property => $value ) {
			$this->inputAndProp = array( $input, $property );

			if ( $this->propertyAlreadySet() ) {
				continue;
			}

			if ( ! $this->propertiesAssignedInCurrentClass( $attribute ) ) {
				continue;
			}

			$defaultPropValue = $this->defaultPropValueAssigned();
			$this->updates[ $input::class ][ $input->name ][ $property ] = $defaultPropValue ?? $value;

			if ( $this->isCollectable( $property ) ) {
				$this->source[ $reflectionClass ][ $input::class ][ $input->name ][] = $property;
			}
		}
	}

	private function shouldUpdate(): bool {
		return self::EXTRACT_AND_UPDATE === $this->flag;
	}

	/** @param class-string<Pos|Assoc|Flag> $attributeName */
	private function inputAlreadyCollectedUsing( string $attributeName, string $inputName ): Pos|Assoc|Flag|null {
		return $this->collect[ $attributeName ][ $inputName ] ?? null;
	}

	private function isCollectable( string|int $property ): bool {
		return ! is_int( $property ) && 'name' !== $property;
	}

	private function propertyAlreadySet(): bool {
		[$input, $prop] = $this->inputAndProp;

		return 'mode' !== $prop && isset( $this->updates[ $input::class ][ $input->name ][ $prop ] );
	}

	/** @param ReflectionAttribute<Pos|Assoc|Flag> $attribute */
	private function propertiesAssignedInCurrentClass( ReflectionAttribute $attribute ): bool {
		return array_key_exists( $this->inputAndProp[1], $attribute->getArguments() );
	}

	private function defaultPropValueAssigned(): mixed {
		[$input, $prop] = $this->inputAndProp;

		return 'default' === $prop && method_exists( $input, 'getUserDefault' )
			? $input->getUserDefault()
			: null;
	}
}
