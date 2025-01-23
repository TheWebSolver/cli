<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Data;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\InputOption;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Enums\InputVariant;

class AssociativeTest extends TestCase {
	#[Test]
	#[DataProvider( 'provideVariousModes' )]
	public function itNormalizesOptionMode( bool $isOptional, bool $isVariadic, int $expected ): void {
		$option = new Associative( 'test', 'This is test', $isVariadic, $isOptional );

		$this->assertSame( 'test', $option->name );
		$this->assertSame( $expected, $option->mode );
		$this->assertSame( 'This is test', $option->desc );
		$this->assertSame( $isVariadic, $option->isVariadic );
		$this->assertSame( $isOptional, $option->valueOptional );
		$this->assertInstanceOf( InputOption::class, $option->input() );
	}

	public static function provideVariousModes(): array {
		return array(
			array( false, false, InputOption::VALUE_REQUIRED ),
			array( true, false, InputOption::VALUE_OPTIONAL ),
			array( true, true, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY ),
		);
	}

	#[Test]
	#[DataProvider( 'providePropsAndDefaultValue' )]
	public function itParsesDefaultValueBasedOnProvidedValueAsWellAsOptionalAndVariadicProp(
		bool $isOptional,
		bool $isVariadic,
		mixed $default,
		mixed $expected
	): void {
		$option = new Associative( 'test', 'desc', $isVariadic, $isOptional, $default );

		$this->assertSame( $expected, $option->default );
		$this->assertInstanceOf( InputOption::class, $option->input() );
	}

	public static function providePropsAndDefaultValue(): array {
		return array(
			array( true, true, 'optionalValueButVariadic', array() ),
			array( false, true, 'requiredValueButVariadic', array() ),
			array( true, true, InputVariant::class, array( 'argument', 'option', 'flag' ) ),
			array( true, true, array( 1, 2, 3 ), array( 1, 2, 3 ) ),
			array( true, false, 'isDefault', 'isDefault' ),
			array( true, false, InputVariant::class, array( 'argument', 'option', 'flag' ) ),
			array( false, false, 'requiredValueCanHaveDefaultValue', 'requiredValueCanHaveDefaultValue' ),
			array( false, true, fn() => 'doesNotQualifyAsClosure', array() ),
			array( true, false, fn() => 'qualifiesIfOptionalAndNotVariadic', 'qualifiesIfOptionalAndNotVariadic' ),
			array( true, true, fn() => array( 'it', 'passes' ), array( 'it', 'passes' ) ),
			array( true, false, fn() => fn() => fn() => InputVariant::class, array( 'argument', 'option', 'flag' ) ),
			array( true, false, fn() => fn() => fn() => 'anything', 'anything' ),
			array( true, true, fn() => fn() => fn() => 'mustBeArrayIfVariadic', array() ),
			array( false, false, 1.23, 1.23 ),
			array( false, true, array( 1.23 ), array( 1.23 ) ),
			array( false, false, 8910, 8910 ),
			array( false, true, array( 8910 ), array( 8910 ) ),
			array( false, false, true, true ),
			array( false, true, array( true ), array( true ) ),
			array( true, false, 1.23, 1.23 ),
			array( true, true, array( 1.23 ), array( 1.23 ) ),
			array( true, false, 8910, 8910 ),
			array( true, true, array( 8910 ), array( 8910 ) ),
			array( true, false, true, true ),
			array( true, true, array( true ), array( true ) ),
		);
	}

	#[Test]
	public function itEnsuresOptionCanBeUsedAsAttribute(): void {
		$reflection = ( new ReflectionClass( SimpleOptionTestCommand::class ) );

		$this->assertNotEmpty( [$attribute] = $reflection->getAttributes( Associative::class ) );

		$option = $attribute->newInstance();

		$this->assertTrue( $option->isVariadic );
		$this->assertSame( 'test', $option->name );
		$this->assertTrue( $option->valueOptional );
		$this->assertSame( 'as Desc', $option->desc );
		$this->assertSame( array( 's' ), $option->shortcut );
		$this->assertSame( array( 'argument', 'option', 'flag' ), $option->suggestedValues );
	}
}

#[Associative( 'test', 'as Desc', true, true, suggestedValues: InputVariant::class, shortcut: array( 's' ) )]
class SimpleOptionTestCommand {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
