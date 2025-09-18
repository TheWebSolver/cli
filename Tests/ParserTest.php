<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use TheWebSolver\Codegarage\Cli\Enums\InputVariant;

class ParserTest extends TestCase {
	#[Test]
	public function itReturnsBackedEnumCasesAsKeyValuePair(): void {
		$this->assertSame( [ 'argument', 'option', 'flag' ], Parser::parseBackedEnumValue( InputVariant::class ) );
		$this->assertSame(
			actual: Parser::parseBackedEnumValue( InputVariant::class, caseAsIndex: true ),
			expected: [
				'Positional'  => 'argument',
				'Associative' => 'option',
				'Flag'        => 'flag',
			]
		);

		$this->assertSame( $names = [ 'One', 'Two', 'Three' ], Parser::parseBackedEnumValue( UnitEnumTest::class ) );
		$this->assertSame( array_combine( $names, $names ), Parser::parseBackedEnumValue( UnitEnumTest::class, caseAsIndex: true ) );
	}

	#[Test]
	public function itNormalizesSuggestedValues(): void {
		$expected = [ 'argument', 'option', 'flag' ];

		$this->assertSame( $expected, Parser::parseInputSuggestion( InputVariant::class ) );

		$this->assertInstanceOf( Closure::class, Parser::parseInputSuggestion( [ $this, 'assertTrue' ] ) );

		$this->assertSame( $expected, Parser::parseInputSuggestion( [ 'argument', 'option', 'flag' ] ) );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

enum UnitEnumTest {
	case One;
	case Two;
	case Three;
}
