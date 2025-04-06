<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Attribute\Command;
use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;

class ConsoleTest extends TestCase {
	#[Test]
	public function itReturnsChildClassAsCommandName(): void {
		$this->assertSame(
			expected: 'create:commandWithoutAttribute',
			actual: Command_Without_Attribute::asCommandName()
		);
	}

	#[Test]
	public function itReturnsCommandNameFromAttribute(): void {
		$this->assertSame(
			expected: 'test:nameFromAttribute',
			actual: Command_With_Attribute::asCommandName()
		);
	}

	#[Test]
	public function itInstantiatesConsoleWithAttributeValues(): void {
		$console = Command_With_Attribute::start();

		$this->assertSame( 'This is a test command.', $console->getDescription() );
		$this->assertTrue( $console->isHidden() );
		$this->assertContains( 'test:cli', $console->getAliases() );
		$this->assertContains( 'test:console', $console->getAliases() );

		$this->assertSame( $console->getName(), $console::asCommandName() );
	}

	#[Test]
	public function ensureSetterGetterWorks(): void {
		$console = new Console();
		$parser  = $this->createStub( InputAttribute::class );

		$this->assertFalse( $console->hasInputAttribute() );
		$this->assertSame( $parser, $console->setInputAttribute( $parser )->getInputAttribute() );
		$this->assertTrue( $console->hasInputAttribute() );

		$childCommand = Command_Without_Attribute::start();

		$this->assertSame( 'create:commandWithoutAttribute', $childCommand->getName() );

		$parser = $this->createMock( InputAttribute::class );

		$parser->expects( $this->once() )->method( 'parse' )->willReturn( $parser );
		$parser->expects( $this->once() )->method( 'toSymfonyInput' )->willReturn( array( 'symfony inputs' ) );

		$childCommand = Command_Without_Attribute::start(
			constructorArgs: array( 'inputAttribute' => $parser, 'name' => 'namespace:command' ) // phpcs:ignore
		);

		$this->assertSame( 'namespace:command', $childCommand->getName() );

		$this->assertTrue( $childCommand->isDefined() );
		$this->assertTrue( $childCommand->hasInputAttribute() );
		$this->assertSame( $parser, $childCommand->getInputAttribute() );
		$this->assertFalse( $childCommand->setDefined( false )->isDefined() );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
class Command_Without_Attribute extends Console {
	public const CLI_NAMESPACE = 'create';

	public function __construct( ?InputAttribute $inputAttribute = null, ?string $name = null ) {
		$inputAttribute && $this->setInputAttribute( $inputAttribute );

		parent::__construct( $name );
	}
}

#[Command(
	/* namespace */    'test',
	/* name */         'nameFromAttribute',
	/* description */  'This is a test command.',
	/* isInternal */   true,
	/* altNames */     'cli',
	/* altNames */     'console'
)]
class Command_With_Attribute extends Console {}
