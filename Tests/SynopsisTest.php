<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use ValueError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Cli\Enum\Synopsis;

class SynopsisTest extends TestCase {
	#[Test]
	#[DataProvider( 'provideValidationData' )]
	public function testValidation( Synopsis $synopsis, mixed $value, bool $throws ): void {
		if ( $throws ) {
			$this->expectException( exception: ValueError::class );
		}

		$expectedValue = Synopsis::Data === $synopsis ? 'synopsis' : $value;

		$this->assertSame( expected: $expectedValue, actual: $synopsis->validate( $value ) );
	}

	/** @return array<array<mixed>> */
	public static function provideValidationData(): array {
		return array(
			array( Synopsis::ShortDescription, 'This is a title', false ),
			array( Synopsis::ShortDescription, 00, true ),
			array( Synopsis::ShortDescription, '', true ),
			array( Synopsis::Description, 'This is a description', false ),
			array( Synopsis::Description, 00, true ),
			array( Synopsis::Description, '', true ),
			array( Synopsis::Optional, true, false ),
			array( Synopsis::Optional, 'true', true ),
			array( Synopsis::Optional, false, false ),
			array( Synopsis::Optional, 'false', true ),
			array( Synopsis::Optional, 0, true ),
			array( Synopsis::Variadic, true, false ),
			array( Synopsis::Variadic, 'true', true ),
			array( Synopsis::Variadic, false, false ),
			array( Synopsis::Variadic, 'false', true ),
			array( Synopsis::Variadic, 0, true ),
			array( Synopsis::Default, true, false ),
			array( Synopsis::Default, 'true', false ),
			array( Synopsis::Default, false, false ),
			array( Synopsis::Default, 'false', false ),
			array( Synopsis::Default, 0, false ),
			array( Synopsis::Default, new \StdClass(), false ),
			array( Synopsis::Default, fn(): bool => 1 === 1, false ),
			array( Synopsis::Default, null, false ),
			array( Synopsis::Type, 'positional', false ),
			array( Synopsis::Type, array(), true ),
			array( Synopsis::Type, 'assoc', false ),
			array( Synopsis::Type, 'flag', false ),
			array( Synopsis::Type, 'switch', true ),
			array( Synopsis::Name, 'valid-name', false ),
			array( Synopsis::Name, 12345, true ),
			array( Synopsis::Name, '', true ),
			array( Synopsis::Data, 'switch', false ),
			array( Synopsis::Data, 'valid-name', false ),
			array( Synopsis::Data, 12345, false ),
			array( Synopsis::Data, '', false ),
			array(
				Synopsis::Value,
				array(
					'optional' => true,
					'name'     => 'boss',
				),
				false,
			),
			array(
				Synopsis::Value,
				array(
					'optional' => true,
					'name'     => '',
				),
				true,
			),
			array(
				Synopsis::Value,
				array(
					'optional' => 'true',
					'name'     => 'boss',
				),
				true,
			),
			array(
				Synopsis::Value,
				array(
					'optional' => false,
					'name'     => 'is-non-empty-string',
				),
				true,
			),
		);
	}
}
