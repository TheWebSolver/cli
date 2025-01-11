<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Cli\Cli;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Cli\Attribute\Command;

class ConsoleTest extends TestCase {
	#[Test]
	public function itReturnsChildClassAsCommandName(): void {
		$container = $this->createMock( Container::class );
		$cli       = $this->createMock( Cli::class );

		$container->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->willReturn( $cli );

		$cli->expects( $this->exactly( 2 ) )
			->method( 'shouldUseClassNameAsCommand' )
			->willReturn( true, false );

		$this->assertSame(
			expected: 'create:commandWithoutAttribute',
			actual: Command_Without_Attribute::asCommandName( $container )
		);

		$this->assertSame(
			expected: '',
			actual: Command_Without_Attribute::asCommandName( $container )
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
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
class Command_Without_Attribute extends Console {
	public const CLI_NAMESPACE = 'create';
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
