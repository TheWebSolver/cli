<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Tester\ApplicationTester;
use TheWebSolver\Codegarage\Cli\Event\CommandSubscriber;

class CommandSubscriberTest extends TestCase {
	#[Test]
	public function itSubscribesToTheSuggestedValues(): void {
		$tester = new ApplicationTester( $app = new Application() );

		$app->setDispatcher( $dispatcher = new EventDispatcher() );
		$app->setAutoExit( false );
		$dispatcher->addSubscriber( $subscriber = new CommandSubscriber() );

		$this->assertSame(
			expected: array( ConsoleEvents::COMMAND => array( array( 'onInputWithSuggestedOptions', -1 ) ) ),
			actual: $subscriber::getSubscribedEvents()
		);

		$command = TestCommand::start();

		$app->add( $command )
			->setCode( static fn( InputInterface $i, OutputInterface $o ) => $o->write( 1 . ' option!' ) );

		$validInput = array(
			'command'  => 'test:command',
			'--option' => 1,
		);

		$invalidInput = array(
			'command'  => 'test:command',
			'--option' => 2,
		);

		$result = $tester->run( $validInput, array( 'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE ) );

		$tester->assertCommandIsSuccessful();

		$this->assertSame( Console::SUCCESS, $result );
		$this->assertStringContainsString( $tester->getDisplay(), '1 option!' );

		$app->setCatchExceptions( false );
		$this->expectException( OutOfBoundsException::class );

		$tester->run( $invalidInput );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

#[AsCommand( 'test:command' )]
#[Associative( 'option', 'desc', default: 'nine', suggestedValues: array( 1, 'two', 'nine' ) )]
class TestCommand extends Console {}
