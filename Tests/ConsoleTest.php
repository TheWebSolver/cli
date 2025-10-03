<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\Application;
use TheWebSolver\Codegarage\Cli\Attribute\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;
use Symfony\Component\Console\Tester\ApplicationTester;

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

		$this->assertTrue( $console->hasInputAttribute() );
		$this->assertSame( $parser, $console->setInputAttribute( $parser )->getInputAttribute() );
		$this->assertTrue( $console->hasInputAttribute() );

		$childCommand = Command_Without_Attribute::start();

		$this->assertSame( 'create:commandWithoutAttribute', $childCommand->getName() );

		$parser = $this->createMock( InputAttribute::class );

		$parser->expects( $this->once() )->method( 'parse' )->willReturn( $parser );
		$parser->expects( $this->once() )->method( 'toSymfonyInput' )->willReturn( [ 'symfony inputs' ] );

		$childCommand = Command_Without_Attribute::start(
			constructorArgs: array( 'inputAttribute' => $parser, 'name' => 'namespace:command' ) // phpcs:ignore
		);

		$this->assertSame( 'namespace:command', $childCommand->getName() );

		$this->assertTrue( $childCommand->isDefined() );
		$this->assertTrue( $childCommand->hasInputAttribute() );
		$this->assertSame( $parser, $childCommand->getInputAttribute() );
		$this->assertFalse( $childCommand->setDefined( false )->isDefined() );
	}

	#[Test]
	public function itEnsuresCommandRunsWithNameAndAliases(): void {
		$tester = new ApplicationTester( $app = new Application() );

		$app->add( new Command_With_Attribute() );
		$app->setAutoExit( false );
		$tester->run( [ 'command' => 'test:nameFromAttribute' ] );

		$tester->assertCommandIsSuccessful( 'Could not discover command: "nameFromAttribute"' );

		foreach ( [ 'cli', 'console' ] as $alias ) {
			$tester->run( [ 'command' => "test:{$alias}" ] );

			$tester->assertCommandIsSuccessful( sprintf( 'Could not discover command using alias: "%s".', $alias ) );
		}
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
	/* help */         'Command help',
	/* isInternal */   true,
	/* altNames */     'cli',
	/* altNames */     'console'
)]
class Command_With_Attribute extends Console {
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$success = $this->getApplication()?->get( 'test:nameFromAttribute' ) === $this;
		$success = $success && ( 'This is a test command.' === $this->getDescription() );
		$success = $success && ( 'Command help' === $this->getHelp() );
		$success = $success && $this->isHidden();

		foreach ( [ 'cli', 'console' ] as $alias ) {
			$success = $success && ( $this->getApplication()?->get( "test:{$alias}" ) === $this );
		}

		return $success ? self::SUCCESS : self::FAILURE;
	}
}
