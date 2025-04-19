<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Data;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use Symfony\Component\Console\Input\InputArgument;
use TheWebSolver\Codegarage\Cli\Enums\InputVariant;

class PositionalTest extends TestCase {
	#[Test]
	#[DataProvider( 'provideOptionalAndVariadicValue' )]
	public function itParsesModeUsingOptionalAndVariadicProp( bool $optional, bool $variadic, int $expected ): void {
		$argument = new Positional( 'test', 'This is test', $variadic, $optional );

		$this->assertSame( 'test', $argument->name );
		$this->assertSame( $expected, $argument->mode );
		$this->assertSame( 'This is test', $argument->desc );
		$this->assertSame( $optional, $argument->isOptional );
		$this->assertSame( $variadic, $argument->isVariadic );
		$this->assertInstanceOf( InputArgument::class, $argument->input() );
	}

	public static function provideOptionalAndVariadicValue(): array {
		return [
			[ true, false, InputArgument::OPTIONAL ],
			[ false, false, InputArgument::REQUIRED ],
			[ true, true, InputArgument::OPTIONAL | InputArgument::IS_ARRAY ],
			[ false, true, InputArgument::REQUIRED | InputArgument::IS_ARRAY ],
		];
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
		return [
			[ true, true, 'optionalValueButIsVariadicCanOnlyBeArray', [] ],
			[ true, true, InputVariant::class, [ 'argument', 'option', 'flag' ] ],
			[ true, true, [ 1, 2, 3 ], [ 1, 2, 3 ] ],
			[ true, false, 'isDefault', 'isDefault' ],
			[ true, false, InputVariant::class, [ 'argument', 'option', 'flag' ] ],
			[ false, true, 'requiredValueButVariadic', null ],
			[ false, false, 'requiredValueCannotHaveDefaultValue', null ],
			[ false, true, fn() => 'nothingQualifiesIfRequiredValue', null ],
			[ true, false, fn() => 'qualifiesIfOptionalAndNotVariadic', 'qualifiesIfOptionalAndNotVariadic' ],
			[ true, true, fn() => [ 'it', 'passes' ], [ 'it', 'passes' ] ],
			[ true, false, fn() => fn() => fn() => InputVariant::class, [ 'argument', 'option', 'flag' ] ],
			[ true, false, fn() => fn() => fn() => 'anything', 'anything' ],
			[ true, true, fn() => fn() => fn() => 'mustBeArrayIfVariadic', [] ],
			[ true, false, 1.23, 1.23 ],
			[ true, true, [ 1.23 ], [ 1.23 ] ],
			[ true, false, 8910, 8910 ],
			[ true, true, [ 8910 ], [ 8910 ] ],
			[ true, false, true, true ],
			[ true, true, [ true ], [ true ] ],
		];
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
		$this->assertSame( [ 'argument', 'option', 'flag' ], $argument->suggestedValues );
	}

	#[Test]
	public function itConvertsInputArgumentToPositional(): void {
		$argument = new InputArgument(
			name: 'arg',
			mode: InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
			description: 'a short details',
			default: [ 'argument', 'option', 'flag' ],
			suggestedValues: fn() => Parser::parseBackedEnumValue( InputVariant::class )
		);

		$positional = Positional::from( $argument );

		$this->assertSame( 'arg', $positional->name );
		$this->assertSame( 'a short details', $positional->desc );
		$this->assertTrue( $positional->isVariadic );
		$this->assertTrue( $positional->isOptional );
		$this->assertSame( $variants = [ 'argument', 'option', 'flag' ], $positional->default );
		$this->assertSame( $variants, ( $positional->suggestedValues )() );
	}

	#[Test]
	public function itMapsConstructorArgs(): void {
		$positional = new Positional( 'test', 'brief', true, false, 5, [ 1 ] );

		$this->assertArrayHasKey( 'default', $info = $positional->__debugInfo() );

		// Default is tested separately for normalization.
		unset( $info['default'] );

		$this->assertSame(
			[
				'name'            => 'test',
				'desc'            => 'brief',
				'isVariadic'      => true,
				'isOptional'      => false,
				'suggestedValues' => [ 1 ],
			],
			$info
		);
	}
}

#[Positional( 'test', 'Using as attribute', true, false, suggestedValues: InputVariant::class )]
class SimpleArgumentTestCommand {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
