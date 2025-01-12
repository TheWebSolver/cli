<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Data;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use Symfony\Component\Console\Input\InputArgument;
use TheWebSolver\Codegarage\Cli\Enum\InputVariant;

class PositionalTest extends TestCase {
	#[Test]
	#[DataProvider( 'provideOptionalAndVariadicValue' )]
	public function itParsesModeUsingOptionalAndVariadicProp( bool $optional, bool $variadic, int $expected ): void {
		$argument = new Positional( 'test', 'This is test', $variadic, $optional );

		$this->assertSame( $expected, $argument->mode );
		$this->assertInstanceOf( InputArgument::class, $argument->input() );
	}

	public static function provideOptionalAndVariadicValue(): array {
		return array(
			array( true, false, InputArgument::OPTIONAL ),
			array( false, false, InputArgument::REQUIRED ),
			array( true, true, InputArgument::OPTIONAL | InputArgument::IS_ARRAY ),
			array( false, true, InputArgument::REQUIRED | InputArgument::IS_ARRAY ),
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
		$argument = new Positional( '', '', $isVariadic, $isOptional, $default );

		$this->assertSame( $expected, $argument->default );
		$this->assertInstanceOf( InputArgument::class, $argument->input() );
	}

	public static function providePropsAndDefaultValue(): array {
		return array(
			array( true, true, 'optionalValueButIsVariadicCanOnlyBeArray', array() ),
			array( true, true, InputVariant::class, array( 'argument', 'option', 'flag' ) ),
			array( true, true, array( 1, 2, 3 ), array( 1, 2, 3 ) ),
			array( true, false, 'isDefault', 'isDefault' ),
			array( true, false, InputVariant::class, array( 'argument', 'option', 'flag' ) ),
			array( false, true, 'requiredValueButVariadic', null ),
			array( false, false, 'requiredValueCannotHaveDefaultValue', null ),
			array( false, true, fn() => 'nothingQualifiesIfRequiredValue', null ),
			array( true, false, fn() => 'qualifiesIfOptionalAndNotVariadic', 'qualifiesIfOptionalAndNotVariadic' ),
			array( true, true, fn() => array( 'it', 'passes' ), array( 'it', 'passes' ) ),
			array( true, false, fn() => fn() => fn() => InputVariant::class, array( 'argument', 'option', 'flag' ) ),
			array( true, false, fn() => fn() => fn() => 'anything', 'anything' ),
			array( true, true, fn() => fn() => fn() => 'mustBeArrayIfVariadic', array() ),
			array( true, false, 1.23, 1.23 ),
			array( true, true, array( 1.23 ), array( 1.23 ) ),
			array( true, false, 8910, 8910 ),
			array( true, true, array( 8910 ), array( 8910 ) ),
			array( true, false, true, true ),
			array( true, true, array( true ), array( true ) ),
		);
	}

	#[Test]
	public function itEnsuresPositionalArgumentCanBeUsedAsAttribute(): void {
		$reflection = ( new ReflectionClass( SimpleArgumentTestCommand::class ) );

		$this->assertNotEmpty( [$attribute] = $reflection->getAttributes( Positional::class ) );

		$argument = $attribute->newInstance();

		$this->assertTrue( $argument->isVariadic );
		$this->assertFalse( $argument->isOptional );
		$this->assertSame( 'test', $argument->name );
		$this->assertSame( 'Using as attribute', $argument->desc );
		$this->assertSame( array( 'argument', 'option', 'flag' ), $argument->suggestedValues );
	}
}

#[Positional( 'test', 'Using as attribute', true, false, suggestedValues: InputVariant::class )]
class SimpleArgumentTestCommand {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
