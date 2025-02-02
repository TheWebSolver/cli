<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\PureArg;

class PureArgTest extends TestCase {
	#[Test]
	public function itEnsuresPureArgumentsSetterGetterResetter(): void {
		$attribute = new class() {
			use PureArg {
				PureArg::walkPure as public;
				PureArg::setPure as public;
			}
		};

		$this->assertFalse( $attribute->hasPure() );

		$arguments = array(
			'name' => 'test',
			'arg1' => true,
			'arg3' => null,
			'arg2' => array(),
			'arg4' => null,
			'arg5' => 5,
		);

		$this->assertSame( array( 'name' => 'test' ), $attribute->setPure( array( 'name' => 'test' ) )->getPure() );
		$this->assertTrue( $attribute->hasPure() );

		$this->assertSame(
			array( 'name' => 'test' ),
			$attribute->setPure( array( 'cannot', 'update' ) )->getPure(),
			'Pure is never overridden unless purged first'
		);

		$this->assertTrue( $attribute->purgePure() );

		$this->assertSame( array(), $attribute->getPure() );
		$this->assertFalse( $attribute->hasPure() );
		$this->assertFalse( $attribute->purgePure() );

		array_walk( $arguments, $attribute->walkPure( ... ) );

		$this->assertCount( 4, $collection = $attribute->getPure() );

		extract( $arguments ); // phpcs:ignore -- test extraction in controlled env.

		foreach ( array( 'name', 'arg1', 'arg2', 'arg5' ) as $expectedKey ) {
			$this->assertArrayHasKey( $expectedKey, $collection );
			$this->assertSame( $$expectedKey, $collection[ $expectedKey ] );
		}
	}

	#[Test]
	public function itAutoDiscoversParamNameAndItsValue(): void {
		$pure = new class() {
			public array $paramNames;
			use PureArg {
				PureArg::discoverPureFrom as public;
			}

			public function discoverableMethod(
				string $string = 'some',
				string $second = null,
				bool $boolean = false,
				callable $ignored = null,
				array $array = array(),
				int $integer = 5,
				bool $simulator = false
			) {
				$this->paramNames = $this->discoverPureFrom( __FUNCTION__, func_get_args() );

				return $this;
			}
		};

		// Simulate all values passed by providing last param value.
		$this->assertCount( 5, $pure->discoverableMethod( simulator: true )->getPure() );

		$this->assertSame(
			array( 'string', 'second', 'boolean', 'ignored', 'array', 'integer', 'simulator' ),
			$pure->paramNames
		);

		foreach ( array( 'string', 'boolean', 'array', 'integer', 'simulator' ) as $expectedKey ) {
			$this->assertArrayHasKey( $expectedKey, $pure->getPure() );
		}

		$pure->purgePure();

		// Simulate only values upto array passed.
		$this->assertCount( 3, $pure->discoverableMethod( array: array() )->getPure() );

		$pure->purgePure();

		// Every param value is pure as none of them is `null`.
		$pure->discoverableMethod( 'one', 'two', true, $pure->hasPure( ... ), array(), 6, true );

		$this->assertCount( 7, $pure->getPure() );
	}
}
