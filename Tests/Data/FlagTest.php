<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Data;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use Symfony\Component\Console\Input\InputOption;

class FlagTest extends TestCase {
	#[Test]
	public function itParsesModeUsingNegatableValue(): void {
		$flag = new Flag( 'test', 'desc', isNegatable: false, shortcut: [ 's', 'h' ] );

		$this->assertFalse( $flag->isNegatable );
		$this->assertSame( [ 's', 'h' ], $flag->shortcut );
		$this->assertSame( InputOption::VALUE_NONE, $flag->mode );
		$this->assertInstanceOf( InputOption::class, $flag->input() );

		$flag = new Flag( 'name', 'desc', isNegatable: true, shortcut: 'shortcut' );

		foreach ( [ 'name', 'desc', 'shortcut' ] as $arg ) {
			$this->assertSame( $arg, $flag->{$arg} );
		}

		$this->assertTrue( $flag->isNegatable );
		$this->assertSame( InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE, $flag->mode );
	}

	#[Test]
	public function itEnsuresFlagOptionCanBeUsedAsAttribute(): void {
		$reflection = new ReflectionClass( SimpleFlagTestCommand::class );

		$this->assertNotEmpty( [$attribute] = $reflection->getAttributes( Flag::class ) );

		$flag = $attribute->newInstance();

		$this->assertTrue( $flag->isNegatable );
		$this->assertSame( 'test', $flag->name );
		$this->assertSame( 't', $flag->shortcut );
		$this->assertSame( 'Using as attribute', $flag->desc );
	}

	#[Test]
	public function itConvertsInputOptionToFlag(): void {
		$option = new InputOption(
			name: 'arg',
			shortcut: 's',
			mode: InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE,
			description: 'a short details'
		);

		$flag = Flag::from( $option );

		$this->assertSame( 'arg', $flag->name );
		$this->assertSame( 'a short details', $flag->desc );
		$this->assertTrue( $flag->isNegatable );
		$this->assertSame( 's', $flag->shortcut );
	}

	#[Test]
	public function itMapsConstructorArgs(): void {
		$flag = new Flag( 'test', 'brief', true, 's' );

		$this->assertSame(
			[
				'name'        => 'test',
				'desc'        => 'brief',
				'isNegatable' => true,
				'shortcut'    => 's',
			],
			$flag->__debugInfo()
		);
	}
}

#[Flag( 'test', 'Using as attribute', isNegatable: true, shortcut: 't' )]
class SimpleFlagTestCommand {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
