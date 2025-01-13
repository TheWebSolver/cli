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
	#[Test]
	public function itSubscribesToTheSuggestedValues(): void {
		$tester = new ApplicationTester( $app = new Application() );

		$app->setDispatcher( $dispatcher = new EventDispatcher() );
		$app->setAutoExit( false );
		$app->setCatchExceptions( false );
		$dispatcher->addSubscriber( $subscriber = new CommandSubscriber() );

		$command = TestCommand::start();

		$app->add( $command )
			->setCode(
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
			fn( InputInterface $i, OutputInterface $o ) => $o->write( $i->getArgument( 'number' ) . ' number' )
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
