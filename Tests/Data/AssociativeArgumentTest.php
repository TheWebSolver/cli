<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Data;

use Closure;
use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Enum\InputVariant;

class AssociativeArgumentTest extends TestCase {
	#[Test]
	#[DataProvider( 'providesDifferentSuggestedValues' )]
	public function itNormalizedSuggestedValues( mixed $value, mixed $expected ): void {
		$associative = new Associative( 'test', 'Suggested Values test', suggestedValues: $value );

		if ( $expected ) {
			$this->assertSame( $expected, $associative->suggestedValues );
		} else {
			$this->assertInstanceOf( Closure::class, $associative->suggestedValues );
		}
	}

	public static function providesDifferentSuggestedValues(): array {
		return array(
			array( array( 1, 2, 3 ), array( 1, 2, 3 ) ),
			array( $fn = fn() => array( 'a', 'b', 'c' ), null ),
			array( InputVariant::class, array( 'positional', 'assoc', 'flag' ) ),
		);
	}

	#[Test]
	public function itEnsuresAssociativeDtoCanBeUsedAsAttribute(): void {
		$reflection = ( new ReflectionClass( SimpleTestCommand::class ) );

		$this->assertNotEmpty( [$attribute] = $reflection->getAttributes( Associative::class ) );

		$associative = $attribute->newInstance();

		$this->assertSame( '--test', $associative->name );
		$this->assertSame( 'Using as attribute', $associative->desc );
		$this->assertSame( array( 'positional', 'assoc', 'flag' ), $associative->suggestedValues );
	}
}

#[Associative( '--test', 'Using as attribute', suggestedValues: InputVariant::class )]
class SimpleTestCommand {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
