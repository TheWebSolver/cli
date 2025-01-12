<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Data;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use Symfony\Component\Console\Input\InputArgument;

class PositionalArgumentTest extends TestCase {
	#[Test]
	#[DataProvider( 'provideOptionalAndVariadicValue' )]
	public function itTransfersDataAsDto( bool $optional, bool $variadic, int $expected ): void {
		$positional = new Positional( 'test', 'This is test', $variadic, $optional );

		$this->assertSame( $expected, $positional->mode );
	}

	public static function provideOptionalAndVariadicValue(): array {
		return array(
			array( true, false, InputArgument::OPTIONAL ),
			array( false, false, InputArgument::REQUIRED ),
			array( true, true, InputArgument::OPTIONAL | InputArgument::IS_ARRAY ),
			array( false, true, InputArgument::REQUIRED | InputArgument::IS_ARRAY ),
		);
	}
}
