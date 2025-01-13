<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\Application;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Attribute\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Tester\ApplicationTester;
use TheWebSolver\Codegarage\Cli\Event\CommandSubscriber;

class CommandSubscriberTest extends TestCase {
	/** @return array{0:ApplicationTester,1:Application,2:TestCommand} */
	private static function getApplicationTester(): array {
		$tester = new ApplicationTester( $app = new Application() );

		$app->setDispatcher( $dispatcher = new EventDispatcher() );
		$app->setAutoExit( false );
		$app->setCatchExceptions( false );
		$dispatcher->addSubscriber( new CommandSubscriber() );

		$app->add( $command = TestCommand::start() );

		return array( $tester, $app, $command );
	}

	#[Test]
	public function itAddsExternalSubscribingMethod(): void {
		[$tester, $app, $command] = $this->getApplicationTester();

		$app->add( $command )->setCode(
			static fn( InputInterface $i, OutputInterface $o ) => $o->write( $i->getArgument( 'number' ) . ' number' )
		);

		CommandSubscriber::disableSuggestionValidation();

		$tester->run(
			$input = array(
				'command'  => 'app:command',
				'--option' => 'not a suggested value but validation is suppressed',
			)
		);

		$tester->assertCommandIsSuccessful();

		CommandSubscriber::disableSuggestionValidation( false );

		$this->expectException( OutOfBoundsException::class );

		$tester->run( $input );
	}

	#[Test]
	public function itSubscribesToTheSuggestedValues(): void {
		[$tester, $app, $command] = $this->getApplicationTester();

		CommandSubscriber::disableSuggestionValidation( false );

		$app->add( $command )->setCode(
			static fn( InputInterface $i, OutputInterface $o ) => $o->write( $i->getOption( 'option' ) . ' option!' )
		);

		$result = $tester->run(
			array(
				'command'  => 'app:command',
				'--option' => 1,
			)
		);

		$tester->assertCommandIsSuccessful();

		$this->assertSame( Console::SUCCESS, $result );
		$this->assertStringContainsString( $tester->getDisplay(), '1 option!' );

		$command->setCode(
			static fn( InputInterface $i, OutputInterface $o ) => $o->write( $i->getArgument( 'number' ) . ' number' )
		);

		$tester->run(
			array(
				'command' => 'app:command',
				'number'  => 'three',
			)
		);

		$tester->assertCommandIsSuccessful();
		$this->assertStringContainsString( 'three number', $tester->getDisplay() );

		$this->expectException( OutOfBoundsException::class );

		$tester->run(
			array(
				'command'  => 'app:command',
				'--option' => 2,
			)
		);
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

#[Command( namespace: 'app', name: 'command', description: 'Must validate suggested values' )]
#[Positional( 'number', 'validate number', suggestedValues: array( 'one', 2, 'three' ) )]
#[Associative( 'option', 'validate option', default: 'nine', suggestedValues: array( 1, 'two', 'nine' ) )]
class TestCommand extends Console {}
