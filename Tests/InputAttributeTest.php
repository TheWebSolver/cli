<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Enums\InputVariant;
use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;

class InputAttributeTest extends TestCase {
	#[Test]
	public function itSetsBaseClassToConsoleIfNotProvided(): void {
		$this->assertSame( Console::class, ( new InputAttribute( MiddleClass::class ) )->getBaseClass() );

		$this->assertSame(
			BaseClass::class,
			( new InputAttribute( MiddleClass::class ) )->till( BaseClass::class )->getBaseClass()
		);
	}

	#[Test]
	public function itParsesAndReplacesParentAttributeWithChildAttribute(): void {
		$parser = InputAttribute::from( MiddleClass::class )->do( InputAttribute::INFER_AND_REPLACE );

		$this->assertCount( 2, $source = $parser->getSource() );

		$this->assertCount( 3, $middleClassSource = $source[ MiddleClass::class ] );

		$positionSource = $middleClassSource[ Positional::class ]['position'];
		$positionInput  = $parser->by( 'position', Positional::class );

		$this->assertEqualsCanonicalizing( array( 'isOptional', 'suggestedValues' ), $positionSource );
		$this->assertEmpty( $positionInput->desc );
		$this->assertFalse( $positionInput->isVariadic );
		$this->assertFalse( $positionInput->isOptional );
		$this->assertNull( $positionInput->default, 'Null if positional input is required' );
		$this->assertSame( array( '123' ), $positionInput->suggestedValues );

		$switchSource = $middleClassSource[ Flag::class ]['switch'];
		$switchInput  = $parser->by( 'switch', Flag::class );

		$this->assertEqualsCanonicalizing( array( 'desc', 'isNegatable' ), $switchSource );
		$this->assertNull( $switchInput->shortcut );
		$this->assertTrue( $switchInput->isNegatable );
		$this->assertSame( 'test based on infer mode', $switchInput->desc );

		$onlyInMiddleSource = $middleClassSource[ Associative::class ]['onlyInMiddle'];
		$onlyInMiddleInput  = $parser->by( 'onlyInMiddle', Associative::class );

		$this->assertEqualsCanonicalizing( array( 'desc', 'shortcut', 'valueOptional', 'isVariadic' ), $onlyInMiddleSource );
		$this->assertTrue( $onlyInMiddleInput->isVariadic );
		$this->assertSame( 'o', $onlyInMiddleInput->shortcut );
		$this->assertTrue( $onlyInMiddleInput->valueOptional );
		$this->assertSame( 'no update or replace', $onlyInMiddleInput->desc );

		$this->assertCount( 1, $baseClassSource = $source[ BaseClass::class ] );

		$keyValueSource = $baseClassSource[ Associative::class ]['keyValue'];
		$keyValueInput  = $parser->by( 'keyValue', Associative::class );

		$this->assertEqualsCanonicalizing( array( 'desc' ), $keyValueSource );
		$this->assertSame( 'key value pair in base class', $keyValueInput->desc );

		$parser = InputAttribute::from( TopClass::class )->do( InputAttribute::INFER_AND_REPLACE );

		$this->assertCount( 2, $source = $parser->getSource() );

		$this->assertCount( 3, $topClassSource = $source[ TopClass::class ] );

		$positionSource = $topClassSource[ Positional::class ]['position'];
		$positionInput  = $parser->by( 'position', Positional::class );

		$this->assertEqualsCanonicalizing( array( 'desc', 'isVariadic', 'suggestedValues' ), $positionSource );
		$this->assertSame( 'from top class', $positionInput->desc );
		$this->assertFalse( $positionInput->isVariadic );
		$this->assertNull( $positionInput->default, 'Null if positional input is optional non-variadic' );
		$this->assertSame( array( 1.0, 2.0, 3.0 ), $positionInput->suggestedValues );

		$keyValueSource = $topClassSource[ Associative::class ]['keyValue'];
		$keyValueInput  = $parser->by( 'keyValue', Associative::class );

		$this->assertEqualsCanonicalizing( array( 'desc', 'isVariadic', 'valueOptional' ), $keyValueSource );
		$this->assertTrue( $keyValueInput->isVariadic );
		$this->assertTrue( $keyValueInput->valueOptional );
		$this->assertSame( 'no update if not named argument', $keyValueInput->desc );

		$switchSource = $topClassSource[ Flag::class ]['switch'];
		$switchInput  = $parser->by( 'switch', Flag::class );

		$this->assertEqualsCanonicalizing( array( 'desc', 'shortcut' ), $switchSource );
		$this->assertSame( 'f', $switchInput->shortcut );
		$this->assertFalse( $switchInput->isNegatable );
		$this->assertSame( 'final switch', $switchInput->desc );

		$this->assertCount( 1, $middleClassSource = $source[ MiddleClass::class ] );

		$onlyInMiddleSource = $middleClassSource[ Associative::class ]['onlyInMiddle'];
		$onlyInMiddleInput  = $parser->by( 'onlyInMiddle', Associative::class );

		$this->assertEqualsCanonicalizing( array( 'desc', 'shortcut', 'valueOptional', 'isVariadic' ), $onlyInMiddleSource );
		$this->assertTrue( $onlyInMiddleInput->isVariadic );
		$this->assertSame( 'o', $onlyInMiddleInput->shortcut );
		$this->assertTrue( $onlyInMiddleInput->valueOptional );
		$this->assertSame( 'no update or replace', $onlyInMiddleInput->desc );

		$this->assertArrayNotHasKey( BaseClass::class, $source );
	}

	#[Test]
	public function itParsesAndOnlyFillsWithParentAttributeIfNotFoundInChildAttribute(): void {
		$parser = InputAttribute::from( MiddleClass::class )->do( InputAttribute::INFER_AND_UPDATE );

		$this->assertCount( 2, $source = $parser->getSource() );
		$this->assertCount( 3, $middleClassSource = $source[ MiddleClass::class ] );
		$this->assertCount( 2, $baseClassSource = $source[ BaseClass::class ] );

		$this->assertEqualsCanonicalizing(
			array( 'isOptional', 'suggestedValues' ),
			$middleClassSource[ Positional::class ]['position']
		);
		$this->assertEqualsCanonicalizing(
			array( 'desc', 'isVariadic', 'default' ),
			$baseClassSource[ Positional::class ]['position']
		);

		$positionInput = $parser->by( 'position', Positional::class );

		$this->assertFalse( $positionInput->isOptional );
		$this->assertTrue( $positionInput->isVariadic );
		$this->assertNull( $positionInput->default, 'Null if positional input is required.' );
		$this->assertSame( 'positional base class attribute', $positionInput->desc );
		$this->assertSame( array( '123' ), $positionInput->suggestedValues );

		$this->assertEqualsCanonicalizing( array( 'desc', 'isNegatable' ), $middleClassSource[ Flag::class ]['switch'] );
		$this->assertArrayNotHasKey( Flag::class, $baseClassSource );

		$switchInput = $parser->by( 'switch', Flag::class );

		$this->assertTrue( $switchInput->isNegatable );
		$this->assertSame( 'test based on infer mode', $switchInput->desc );

		$this->assertEqualsCanonicalizing(
			array( 'desc', 'shortcut', 'valueOptional', 'isVariadic' ),
			$middleClassSource[ Associative::class ]['onlyInMiddle']
		);

		$onlyInMiddleInput = $parser->by( 'onlyInMiddle', Associative::class );

		$this->assertTrue( $onlyInMiddleInput->isVariadic );
		$this->assertSame( 'o', $onlyInMiddleInput->shortcut );
		$this->assertTrue( $onlyInMiddleInput->valueOptional );
		$this->assertSame( 'no update or replace', $onlyInMiddleInput->desc );

		$this->assertEqualsCanonicalizing( array( 'desc' ), $baseClassSource[ Associative::class ]['keyValue'] );

		$keyValueInput = $parser->by( 'keyValue', Associative::class );

		$this->assertSame( 'key value pair in base class', $keyValueInput->desc );
	}
	#[Test]
	public function itParsesAttributesFromMultiInheritanceHierarchy(): void {
		$parser = InputAttribute::from( TopClass::class )->do( InputAttribute::INFER_AND_UPDATE );

		$this->assertCount( 3, $source = $parser->getSource() );
		$this->assertCount( 2, $topClassSource = $source[ TopClass::class ] );
		$this->assertCount( 3, $middleClassSource = $source[ MiddleClass::class ] );
		$this->assertCount( 2, $baseClassSource = $source[ BaseClass::class ] );

		$positionInput = $parser->by( 'position', Positional::class );
		$this->assertEqualsCanonicalizing(
			array( 'desc', 'isVariadic', 'suggestedValues' ),
			$topClassSource[ Positional::class ]['position']
		);
		$this->assertSame( 'from top class', $positionInput->desc );
		$this->assertFalse( $positionInput->isVariadic );
		$this->assertSame( array( 1.0, 2.0, 3.0 ), $positionInput->suggestedValues );

		$this->assertEqualsCanonicalizing( array( 'isOptional' ), $middleClassSource[ Positional::class ]['position'] );
		$this->assertFalse( $positionInput->isOptional );

		$this->assertEqualsCanonicalizing( array( 'default' ), $baseClassSource[ Positional::class ]['position'] );
		$this->assertNull( $positionInput->default, 'Sourced but normalized to "null" for required non-variadic' );

		$switchInput = $parser->by( 'switch', Flag::class );

		$this->assertEqualsCanonicalizing( array( 'desc', 'shortcut' ), $topClassSource[ Flag::class ]['switch'] );
		$this->assertSame( 'final switch', $switchInput->desc );
		$this->assertSame( 'f', $switchInput->shortcut );

		$this->assertEqualsCanonicalizing( array( 'isNegatable' ), $middleClassSource[ Flag::class ]['switch'] );
		$this->assertTrue( $switchInput->isNegatable );

		$this->assertArrayNotHasKey( Flag::class, $baseClassSource, 'Nothing left to source' );

		$this->assertArrayNotHasKey( Associative::class, $topClassSource, 'Value passed without named arguments' );
		$this->assertArrayNotHasKey(
			'keyValue',
			$middleClassSource[ Associative::class ],
			'Attribute with "keyValue" name does not exists to parse'
		);

		$keyValueInput = $parser->by( 'keyValue', Associative::class );

		$this->assertEqualsCanonicalizing( array( 'desc' ), $baseClassSource[ Associative::class ]['keyValue'] );

		$this->assertFalse( $keyValueInput->isVariadic );
		$this->assertFalse( $keyValueInput->valueOptional );
		$this->assertSame( 'key value pair in base class', $keyValueInput->desc );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

#[Positional(
	name: 'position',
	desc: 'positional base class attribute',
	isVariadic: true,
	default: InputVariant::class,
	suggestedValues: array( 1, 2, 3 ),
)]
#[Associative( 'keyValue', 'key value pair in base class' )]
#[Flag( name: 'switch', isNegatable: true )]
class BaseClass extends Console {}

#[Positional( name: 'position', isOptional: false, suggestedValues: '123' )]
#[Flag( name: 'switch', desc: 'test based on infer mode', isNegatable: true )]
#[Associative(
	name: 'onlyInMiddle',
	desc: 'no update or replace',
	shortcut: 'o',
	valueOptional: true,
	isVariadic: true
)]
class MiddleClass extends BaseClass {}

#[Associative( 'keyValue', 'no update if not named argument', true, true )]
#[Flag( name: 'switch', desc: 'final switch', shortcut: 'f' )]
#[Positional(
	name: 'position',
	desc: 'from top class',
	isVariadic: false,
	suggestedValues: array( 1.0, 2.0, 3.0 )
)]
class TopClass extends MiddleClass {}
