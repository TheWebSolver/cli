<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use Symfony\Component\Console\Input\InputOption;

class FlagTest extends TestCase {
	#[Test]
	public function itParsesModeUsingNegatableValue(): void {
		$flag = new Flag( 'test', 'desc', isNegatable: false, shortcut: array( 's', 'h' ) );

		$this->assertSame( InputOption::VALUE_NONE, $flag->mode );
		$this->assertSame( array( 's', 'h' ), $flag->shortcut );
		$this->assertInstanceOf( InputOption::class, $flag->input() );

		$flag = new Flag( 'name', 'desc', isNegatable: true, shortcut: 'shortcut' );

		$this->assertSame( InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE, $flag->mode );

		foreach ( array( 'name', 'desc', 'shortcut' ) as $arg ) {
			$this->assertSame( $arg, $flag->{$arg} );
		}
	}

	#[Test]
	public function itEnsuresFlagOptionCanBeUsedAsAttribute(): void {
		$reflection = new ReflectionClass( SimpleFlagTestCommand::class );

		$this->assertNotEmpty( [$attribute] = $reflection->getAttributes( Flag::class ) );

		$associative = $attribute->newInstance();

		$this->assertSame( 'test', $associative->name );
		$this->assertSame( 'Using as attribute', $associative->desc );
		$this->assertSame( 't', $associative->shortcut );
	}
}

#[Flag( 'test', 'Using as attribute', isNegatable: true, shortcut: 't' )]
class SimpleFlagTestCommand {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
