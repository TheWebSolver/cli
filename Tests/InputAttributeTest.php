<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Console;
use PHPUnit\Framework\Attributes\Depends;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use Symfony\Component\Console\Input\InputOption;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Input\InputArgument;
use TheWebSolver\Codegarage\Cli\Enums\InputVariant;
use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;

class InputAttributeTest extends TestCase {
	#[Test]
	public function verifySetterAndGetter(): void {
		$this->assertSame( Console::class, ( $parser = ( new InputAttribute( MiddleClass::class ) ) )->getBaseClass() );

		$this->assertSame( MiddleClass::class, $parser->getTargetReflection()->getName() );
		$this->assertEmpty( $parser->getCollection() );
		$this->assertEmpty( $parser->getSource() );
		$this->assertEmpty( $parser->getSuggestion() );
		$this->assertNull( $parser->getInputBy( 'input-name' ) );
		$this->assertTrue( $parser->isValid() );

		$this->assertSame(
			BaseClass::class,
			( $parser = ( new InputAttribute( MiddleClass::class ) ) )->till( BaseClass::class )->getBaseClass()
		);

		$this->assertSame(
			BaseClass::class,
			$parser->till( Console::class )->getBaseClass(),
			'Cannot update base class.'
		);

		$debug = $parser->__debugInfo();

		$this->assertFalse( $debug['status'] );
		$this->assertEmpty( $debug['hierarchy'] );
		$this->assertFalse( $debug['target']['till'] );
		$this->assertSame( MiddleClass::class, $debug['target']['from'] );
		$this->assertSame( BaseClass::class, $debug['target']['base'] );
	}

	#[Test]
	public function itParsesAndReplacesParentAttributeWithChildAttribute(): void {
		$parser = InputAttribute::from( MiddleClass::class )
			->register( InputAttribute::INFER_AND_REPLACE )
			->parse();

		$this->assertCount( 2, $source = $parser->getSource() );

		$this->assertCount( 3, $middleClassSource = $source[ MiddleClass::class ] );

		$positionSource = $middleClassSource[ Positional::class ]['position'];
		$positionInput  = $parser->getInputBy( 'position', Positional::class );

		$this->assertEqualsCanonicalizing( [ 'isOptional', 'suggestedValues' ], $positionSource );
		$this->assertEmpty( $positionInput->desc );
		$this->assertFalse( $positionInput->isVariadic );
		$this->assertFalse( $positionInput->isOptional );
		$this->assertNull( $positionInput->default, 'Null if positional input is required' );
		$this->assertSame( [ '123' ], $positionInput->suggestedValues );

		$switchSource = $middleClassSource[ Flag::class ]['switch'];
		$switchInput  = $parser->getInputBy( 'switch', Flag::class );

		$this->assertEqualsCanonicalizing( [ 'desc', 'isNegatable' ], $switchSource );
		$this->assertNull( $switchInput->shortcut );
		$this->assertTrue( $switchInput->isNegatable );
		$this->assertSame( 'test based on infer mode', $switchInput->desc );

		$onlyInMiddleSource = $middleClassSource[ Associative::class ]['onlyinmiddle'];
		$onlyInMiddleInput  = $parser->getInputBy( 'onlyInMiddle', Associative::class );

		$this->assertEqualsCanonicalizing( [ 'desc', 'shortcut', 'isOptional', 'isVariadic' ], $onlyInMiddleSource );
		$this->assertTrue( $onlyInMiddleInput->isVariadic );
		$this->assertSame( 'o', $onlyInMiddleInput->shortcut );
		$this->assertTrue( $onlyInMiddleInput->isOptional );
		$this->assertSame( 'no update or replace', $onlyInMiddleInput->desc );

		$this->assertCount( 1, $baseClassSource = $source[ BaseClass::class ] );

		$keyValueSource = $baseClassSource[ Associative::class ]['key-value'];
		$keyValueInput  = $parser->getInputBy( 'key-Value', Associative::class );

		$this->assertEqualsCanonicalizing( [ 'desc' ], $keyValueSource );
		$this->assertSame( 'key value pair in base class', $keyValueInput->desc );

		$parser = InputAttribute::from( TopClass::class )
			->register( InputAttribute::INFER_AND_REPLACE )
			->parse();

		$this->assertCount( 2, $source = $parser->getSource() );

		$this->assertCount( 3, $topClassSource = $source[ TopClass::class ] );

		$positionSource = $topClassSource[ Positional::class ]['position'];
		$positionInput  = $parser->getInputBy( 'position', Positional::class );

		$this->assertEqualsCanonicalizing( [ 'desc', 'isVariadic', 'suggestedValues' ], $positionSource );
		$this->assertSame( 'from top class', $positionInput->desc );
		$this->assertFalse( $positionInput->isVariadic );
		$this->assertNull( $positionInput->default, 'Null if positional input is optional non-variadic' );
		$this->assertSame( [ 1.0, 2.0, 3.0 ], $positionInput->suggestedValues );

		$keyValueSource = $topClassSource[ Associative::class ]['key-value'];
		$keyValueInput  = $parser->getInputBy( 'key-Value', Associative::class );

		$this->assertEqualsCanonicalizing(
			[ 'desc', 'isVariadic', 'isOptional', 'default' ],
			$keyValueSource
		);
		$this->assertTrue( $keyValueInput->isVariadic );
		$this->assertTrue( $keyValueInput->isOptional );
		$this->assertSame( 'updated irrespective of no-named argument', $keyValueInput->desc );

		$switchSource = $topClassSource[ Flag::class ]['switch'];
		$switchInput  = $parser->getInputBy( 'switch', Flag::class );

		$this->assertEqualsCanonicalizing( [ 'desc', 'shortcut' ], $switchSource );
		$this->assertSame( 'f', $switchInput->shortcut );
		$this->assertFalse( $switchInput->isNegatable );
		$this->assertSame( 'final switch', $switchInput->desc );

		$this->assertCount( 1, $middleClassSource = $source[ MiddleClass::class ] );

		$onlyInMiddleSource = $middleClassSource[ Associative::class ]['onlyinmiddle'];
		$onlyInMiddleInput  = $parser->getInputBy( 'onlyInMiddle', Associative::class );

		$this->assertEqualsCanonicalizing( [ 'desc', 'shortcut', 'isOptional', 'isVariadic' ], $onlyInMiddleSource );
		$this->assertTrue( $onlyInMiddleInput->isVariadic );
		$this->assertSame( 'o', $onlyInMiddleInput->shortcut );
		$this->assertTrue( $onlyInMiddleInput->isOptional );
		$this->assertSame( 'no update or replace', $onlyInMiddleInput->desc );

		$this->assertArrayNotHasKey( BaseClass::class, $source );
	}

	#[Test]
	public function itParsesAndOnlyFillsWithParentAttributeIfNotFoundInChildAttribute(): void {
		$parser = InputAttribute::from( MiddleClass::class )->register()->parse();

		$this->assertCount( 2, $source = $parser->getSource() );
		$this->assertCount( 3, $middleClassSource = $source[ MiddleClass::class ] );
		$this->assertCount( 2, $baseClassSource = $source[ BaseClass::class ] );

		$this->assertEqualsCanonicalizing(
			[ 'isOptional', 'suggestedValues' ],
			$middleClassSource[ Positional::class ]['position']
		);
		$this->assertEqualsCanonicalizing(
			[ 'desc', 'isVariadic', 'default' ],
			$baseClassSource[ Positional::class ]['position']
		);

		$positionInput = $parser->getInputBy( 'position', Positional::class );

		$this->assertFalse( $positionInput->isOptional );
		$this->assertTrue( $positionInput->isVariadic );
		$this->assertNull( $positionInput->default, 'Null if positional input is required.' );
		$this->assertSame( 'positional base class attribute', $positionInput->desc );
		$this->assertSame( [ '123' ], $positionInput->suggestedValues );

		$this->assertEqualsCanonicalizing( [ 'desc', 'isNegatable' ], $middleClassSource[ Flag::class ]['switch'] );
		$this->assertArrayNotHasKey( Flag::class, $baseClassSource );

		$switchInput = $parser->getInputBy( 'switch', Flag::class );

		$this->assertTrue( $switchInput->isNegatable );
		$this->assertSame( 'test based on infer mode', $switchInput->desc );

		$this->assertEqualsCanonicalizing(
			[ 'desc', 'shortcut', 'isOptional', 'isVariadic' ],
			$middleClassSource[ Associative::class ]['onlyinmiddle']
		);

		$onlyInMiddleInput = $parser->getInputBy( 'onlyInMiddle', Associative::class );

		$this->assertTrue( $onlyInMiddleInput->isVariadic );
		$this->assertSame( 'o', $onlyInMiddleInput->shortcut );
		$this->assertTrue( $onlyInMiddleInput->isOptional );
		$this->assertSame( 'no update or replace', $onlyInMiddleInput->desc );

		$this->assertEqualsCanonicalizing( [ 'desc' ], $baseClassSource[ Associative::class ]['key-value'] );

		$keyValueInput = $parser->getInputBy( 'key-Value', Associative::class );

		$this->assertSame( 'key value pair in base class', $keyValueInput->desc );
	}
	#[Test]
	public function itParsesAttributesFromMultiInheritanceHierarchy(): InputAttribute {
		$parser = InputAttribute::from( TopClass::class )->register()->parse();

		$this->assertCount( 3, $source = $parser->getSource() );
		$this->assertCount( 3, $topClassSource = $source[ TopClass::class ] );
		$this->assertCount( 3, $middleClassSource = $source[ MiddleClass::class ] );
		$this->assertCount( 1, $baseClassSource = $source[ BaseClass::class ] );

		$positionInput = $parser->getInputBy( 'position', Positional::class );
		$this->assertEqualsCanonicalizing(
			[ 'desc', 'isVariadic', 'suggestedValues' ],
			$topClassSource[ Positional::class ]['position']
		);
		$this->assertSame( 'from top class', $positionInput->desc );
		$this->assertFalse( $positionInput->isVariadic );
		$this->assertSame( [ 1.0, 2.0, 3.0 ], $positionInput->suggestedValues );

		$this->assertEqualsCanonicalizing( [ 'isOptional' ], $middleClassSource[ Positional::class ]['position'] );
		$this->assertFalse( $positionInput->isOptional );

		$this->assertEqualsCanonicalizing( [ 'default' ], $baseClassSource[ Positional::class ]['position'] );
		$this->assertNull( $positionInput->default, 'Sourced but normalized to "null" for required non-variadic' );

		$switchInput = $parser->getInputBy( 'switch', Flag::class );
		$this->assertEqualsCanonicalizing( [ 'desc', 'shortcut' ], $topClassSource[ Flag::class ]['switch'] );
		$this->assertSame( 'final switch', $switchInput->desc );
		$this->assertSame( 'f', $switchInput->shortcut );

		$this->assertEqualsCanonicalizing( [ 'isNegatable' ], $middleClassSource[ Flag::class ]['switch'] );
		$this->assertTrue( $switchInput->isNegatable );

		$this->assertArrayNotHasKey( Flag::class, $baseClassSource, 'Nothing left to source' );

		$keyValueInput = $parser->getInputBy( 'key-Value', Associative::class );
		$this->assertEqualsCanonicalizing(
			[ 'desc', 'isVariadic', 'isOptional', 'default' ],
			$topClassSource[ Associative::class ]['key-value']
		);
		$this->assertArrayNotHasKey( Associative::class, $baseClassSource );

		$this->assertTrue( $keyValueInput->isVariadic );
		$this->assertTrue( $keyValueInput->isOptional );
		$this->assertSame( 'updated irrespective of no-named argument', $keyValueInput->desc );

		return $parser;
	}

	#[Test]
	#[Depends( 'itParsesAttributesFromMultiInheritanceHierarchy' )]
	public function ensureGettersAndDebugInfo( InputAttribute $parser ): void {
		$this->assertFalse( $parser->isValid() );
		$this->assertSame( Console::class, $parser->getBaseClass() );

		$debug = $parser->__debugInfo();

		$this->assertTrue( $debug['status'] );
		$this->assertSame( BaseClass::class, $debug['target']['till'] );
		$this->assertSame( [ TopClass::class, MiddleClass::class, BaseClass::class ], $debug['hierarchy'] );

		$this->assertCount( 3, $collection = $parser->getCollection() );
		$this->assertCount( 2, $collection[ Associative::class ] );
		$this->assertCount( 1, $collection[ Positional::class ] );
		$this->assertCount( 1, $collection[ Flag::class ] );

		$this->assertCount( 1, $suggestions = $parser->getSuggestion() );
		$this->assertSame( [ 1.0, 2.0, 3.0 ], $suggestions['position'] );
	}

	#[Test]
	#[Depends( 'itParsesAttributesFromMultiInheritanceHierarchy' )]
	public function itTransformsParsedCollectionToDefinitions( InputAttribute $parser ): void {
		$this->assertCount( 3, $definitions = $parser->toSymfonyInput() );

		foreach ( $definitions as $attributeName => $collection ) {
			$this->assertCount( Associative::class === $attributeName ? 2 : 1, $collection );

			foreach ( $collection as $input ) {
				$this->assertInstanceOf(
					Positional::class === $attributeName ? InputArgument::class : InputOption::class,
					$input
				);
			}
		}
	}

	#[Test]
	#[Depends( 'itParsesAttributesFromMultiInheritanceHierarchy' )]
	public function itTransformsCollectionToFlattenedArray( InputAttribute $parser ): void {
		$flattened = $parser->toFlattenedArray();

		$this->assertCount( 4, $flattened );
		$this->assertEqualsCanonicalizing(
			[ 'position', 'key-value', 'onlyinmiddle', 'switch' ],
			array_column( $flattened, 'name' )
		);
	}

	#[Test]
	#[Depends( 'itParsesAttributesFromMultiInheritanceHierarchy' )]
	public function itEnsuresInvocableParserReturnsSameCollection( InputAttribute $parser ): void {
		$this->assertEquals( ( new InputAttribute( TopClass::class ) )(), $parser->getCollection() );
	}

	#[Test]
	public function itEnsuresParsingStopsOnTheGivenInheritanceHierarchyParentClass(): void {
		$parser = InputAttribute::from( TopClass::class )->till( BaseClass::class )->register()->parse();
		$debug  = $parser->__debugInfo();

		$this->assertSame( TopClass::class, $debug['target']['from'] );
		$this->assertSame( MiddleClass::class, $debug['target']['till'] );
		$this->assertSame( BaseClass::class, $debug['target']['base'] );
		$this->assertSame( [ TopClass::class, MiddleClass::class ], $debug['hierarchy'] );

		$parser = InputAttribute::from( UnnamedTarget::class )->till( MiddleTarget::class )->register()->parse();
		$debug  = $parser->__debugInfo();

		$this->assertSame( $debug['target']['from'], UnnamedTarget::class );
		$this->assertSame( $debug['target']['till'], UnnamedTarget::class );
		$this->assertSame( $debug['target']['base'], MiddleTarget::class );
		$this->assertSame( [ UnnamedTarget::class ], $debug['hierarchy'] );
	}

	#[Test]
	public function itEnsuresUnnamedAttributesAreRecursivelyUpdated(): void {
		$parser     = InputAttribute::from( UnnamedTarget::class )->register()->parse();
		$positional = $parser->getInputBy( 'unnamed', Positional::class );

		$this->assertTrue( $positional->isVariadic );
		$this->assertFalse( $positional->isOptional );
		$this->assertSame( 'target desc', $positional->desc );

		$parser->add( new Positional( 'unnamed', 'later added' ) );

		$unnamed = $parser->getInputBy( 'unnamed', Positional::class );

		$this->assertSame( 'later added', $unnamed->desc );
	}

	#[Test]
	public function itEnsuesNewlyAddedInputCanOverrideParsed(): void {
		$parser = InputAttribute::from( TopClass::class )->register()->parse();
		$parsed = $parser->getInputBy( 'position', Positional::class );

		$this->assertFalse( $parsed->isVariadic );
		$this->assertFalse( $parsed->isOptional );

		$parser->add( new Positional( 'position', isVariadic: true ) );

		$updated = $parser->getInputBy( 'position', Positional::class );

		$this->assertSame( 'from top class', $updated->desc );

		$this->assertTrue( $updated->isVariadic );
		$this->assertFalse( $updated->isOptional );

		$parser->add(
			new Positional( 'position', isVariadic: true ),
			InputAttribute::INFER_AND_REPLACE
		);

		$replaced = $parser->getInputBy( 'position', Positional::class );

		$this->assertTrue( $replaced->isVariadic );
		$this->assertTrue( $replaced->isOptional );
		$this->assertSame( '', $replaced->desc );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

#[Positional(
	name: 'position',
	desc: 'positional base class attribute',
	isVariadic: true,
	default: InputVariant::class,
	suggestedValues: [ 1, 2, 3 ],
)]
#[Associative( 'key-Value', 'key value pair in base class' )]
#[Flag( name: 'switch', isNegatable: true )]
class BaseClass extends Console {}

#[Positional( isOptional: false, name: 'position', suggestedValues: '123' )]
#[Flag( name: 'switch', desc: 'test based on infer mode', isNegatable: true )]
#[Associative(
	desc: 'no update or replace',
	shortcut: 'o',
	isOptional: true,
	name: 'onlyInMiddle',
	isVariadic: true
)]
class MiddleClass extends BaseClass {}

#[Associative( 'key-Value', 'updated irrespective of no-named argument', true, true, default: InputVariant::class )]
#[Flag( name: 'switch', desc: 'final switch', shortcut: 'f' )]
#[Positional(
	name: 'position',
	desc: 'from top class',
	isVariadic: false,
	suggestedValues: [ 1.0, 2.0, 3.0 ]
)]
class TopClass extends MiddleClass {}

#[Positional( 'unnamed', '', false, false )]
class BaseTarget extends Console {}

#[Positional( 'unnamed', '', true )]
class MiddleTarget extends BaseTarget {}

#[Positional( 'unnamed', desc: 'target desc' )]
class UnnamedTarget extends MiddleTarget {}
