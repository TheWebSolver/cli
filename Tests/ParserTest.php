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
		$this->assertSame( array( 'argument', 'option', 'flag' ), Parser::parseBackedEnumValue( InputVariant::class ) );

		$this->assertSame(
			actual: Parser::parseBackedEnumValue( InputVariant::class, caseAsIndex: true ),
			expected: array(
				'Positional'  => 'argument',
				'Associative' => 'option',
				'Flag'        => 'flag',
			)
		);
	}

	#[Test]
	public function itNormalizesSuggestedValues(): void {
		$expected = array( 'argument', 'option', 'flag' );

		$this->assertSame( $expected, Parser::parseInputSuggestion( InputVariant::class ) );

		$this->assertInstanceOf( Closure::class, Parser::parseInputSuggestion( array( $this, 'assertTrue' ) ) );

		$this->assertSame( $expected, Parser::parseInputSuggestion( array( 'argument', 'option', 'flag' ) ) );
	}
}
