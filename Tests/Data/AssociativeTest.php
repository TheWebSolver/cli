<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Data;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
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
		$this->assertSame( $isOptional, $option->isOptional );
		$this->assertInstanceOf( InputOption::class, $option->input() );
	}

	public static function provideVariousModes(): array {
		return [
			[ false, false, InputOption::VALUE_REQUIRED ],
			[ true, false, InputOption::VALUE_OPTIONAL ],
			[ true, true, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY ],
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
		$option = new Associative( 'test', 'desc', $isVariadic, $isOptional, $default );

		$this->assertSame( $expected, $option->default );
		$this->assertInstanceOf( InputOption::class, $option->input() );
	}

	public static function providePropsAndDefaultValue(): array {
		return [
			[ true, true, 'optionalValueButVariadic', [] ],
			[ false, true, 'requiredValueButVariadic', [] ],
			[ true, true, InputVariant::class, [ 'argument', 'option', 'flag' ] ],
			[ true, false, InputVariant::Associative, 'option' ],
			[ true, true, InputVariant::Associative, [] ],
			[ true, true, [ 1, 2, 3 ], [ 1, 2, 3 ] ],
			[ true, false, 'isDefault', 'isDefault' ],
			[ true, false, InputVariant::class, [ 'argument', 'option', 'flag' ] ],
			[ false, false, 'requiredValueCanHaveDefaultValue', 'requiredValueCanHaveDefaultValue' ],
			[ false, true, fn() => 'doesNotQualifyAsClosure', [] ],
			[ true, false, fn() => 'qualifiesIfOptionalAndNotVariadic', 'qualifiesIfOptionalAndNotVariadic' ],
			[ true, true, fn() => [ 'it', 'passes' ], [ 'it', 'passes' ] ],
			[ true, false, fn() => fn() => fn() => InputVariant::class, [ 'argument', 'option', 'flag' ] ],
			[ true, false, fn() => fn() => fn() => 'anything', 'anything' ],
			[ true, true, fn() => fn() => fn() => 'mustBeArrayIfVariadic', [] ],
			[ false, false, 1.23, 1.23 ],
			[ false, true, [ 1.23 ], [ 1.23 ] ],
			[ false, false, 8910, 8910 ],
			[ false, true, [ 8910 ], [ 8910 ] ],
			[ false, false, true, true ],
			[ false, true, [ true ], [ true ] ],
			[ true, false, 1.23, 1.23 ],
			[ true, true, [ 1.23 ], [ 1.23 ] ],
			[ true, false, 8910, 8910 ],
			[ true, true, [ 8910 ], [ 8910 ] ],
			[ true, false, true, true ],
			[ true, true, [ true ], [ true ] ],
		];
	}

	#[Test]
	public function itEnsuresOptionCanBeUsedAsAttribute(): void {
		$reflection = ( new ReflectionClass( SimpleOptionTestCommand::class ) );

		$this->assertNotEmpty( [$attribute] = $reflection->getAttributes( Associative::class ) );

		$option = $attribute->newInstance();

		$this->assertTrue( $option->isVariadic );
		$this->assertSame( 'test', $option->name );
		$this->assertTrue( $option->isOptional );
		$this->assertSame( 'as Desc', $option->desc );
		$this->assertSame( [ 's' ], $option->shortcut );
		$this->assertSame( [ 'argument', 'option', 'flag' ], $option->suggestedValues );
	}

	#[Test]
	public function itConvertsInputOptionToAssociative(): void {
		$option = new InputOption(
			name: 'arg',
			shortcut: 's',
			mode: InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
			description: 'a short details',
			default: [ 'argument', 'option', 'flag' ],
			suggestedValues: fn() => Parser::parseBackedEnumValue( InputVariant::class )
		);

		$associative = Associative::from( $option );

		$this->assertSame( 'arg', $associative->name );
		$this->assertSame( 'a short details', $associative->desc );
		$this->assertTrue( $associative->isVariadic );
		$this->assertTrue( $associative->isOptional );
		$this->assertSame( $variants = [ 'argument', 'option', 'flag' ], $associative->default );
		$this->assertSame( 's', $associative->shortcut );
		$this->assertSame( $variants, ( $associative->suggestedValues )() );
	}

	#[Test]
	public function itMapsConstructorArgs(): void {
		$associative = new Associative( 'test', 'brief', true, false, 5, 's', [ 1 ] );

		$this->assertArrayHasKey( 'default', $info = $associative->__debugInfo() );

		// Default is tested separately for normalization.
		unset( $info['default'] );

		$this->assertSame(
			[
				'name'            => 'test',
				'desc'            => 'brief',
				'isVariadic'      => true,
				'isOptional'      => false,
				'shortcut'        => 's',
				'suggestedValues' => [ 1 ],
			],
			$info
		);
	}
}

#[Associative( 'test', 'as Desc', true, true, suggestedValues: InputVariant::class, shortcut: [ 's' ] )]
class SimpleOptionTestCommand {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
