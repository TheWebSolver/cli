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
use TheWebSolver\Codegarage\Cli\Attribute\DoNotValidateSuggestedValues;

class CommandSubscriberTest extends TestCase {
	/**
	 * @param class-string<Console> $className
	 * @return array{0:ApplicationTester,1:Console2:Application,2:TestCommand}
	 */
	// phpcs:ignore quiz.Commenting.FunctionComment.IncorrectTypeHint
	private static function getApplicationTester( string $className = TestCommand::class ): array {
		$tester = new ApplicationTester( $app = new Application() );

		$app->setDispatcher( $dispatcher = new EventDispatcher() );
		$app->setAutoExit( false );
		$app->setCatchExceptions( false );
		$dispatcher->addSubscriber( new CommandSubscriber() );

		$app->add( $command = $className::start() );

		return array( $tester, $command, $app );
	}

	#[Test]
	public function itAddsExternalSubscribingMethod(): void {
		[$tester, $command] = $this->getApplicationTester();

		$command->setCode(
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
		[$tester, $command] = $this->getApplicationTester();

		CommandSubscriber::disableSuggestionValidation( false );

		$command->setCode(
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

	#[Test]
	public function itDoesNotValidateSuggestedValuesBasedOnAttribute(): void {
		[$tester, $command] = $this->getApplicationTester( TestNoValidate::class );

		$command->setCode(
			static fn( InputInterface $i, OutputInterface $o ) => $o->write( $i->getArgument( 'number' ) )
		);

		$tester->run(
			array(
				'command' => 'no:validate',
				'number'  => 'invalid but will not be validated',
			)
		);

		$tester->assertCommandIsSuccessful();
		$this->assertStringContainsString( 'invalid but will not be validated', $tester->getDisplay() );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

#[Command( namespace: 'app', name: 'command', description: 'Must validate suggested values' )]
#[Positional( 'number', 'validate number', suggestedValues: array( 'one', 2, 'three' ) )]
#[Associative( 'option', 'validate option', default: 'nine', suggestedValues: array( 1, 'two', 'nine' ) )]
class TestCommand extends Console {}

#[Command( namespace: 'no', name: 'validate', description: 'Must not validate suggested values' )]
#[Positional( 'number', 'skip number validation', suggestedValues: array( 'one', 2, 'three' ) )]
#[DoNotValidateSuggestedValues]
class TestNoValidate extends Console {}
