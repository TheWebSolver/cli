<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\Tester\ApplicationTester;
use TheWebSolver\Codegarage\Cli\Event\CommandSubscriber;
use TheWebSolver\Codegarage\Cli\Attribute\DoNotValidateSuggestedValues;

class CommandSubscriberTest extends TestCase {
	/**
	 * @param class-string<Console> $className
	 * @return array{0:ApplicationTester,1:Console:Application,2:TestCommand}
	 */
	// phpcs:ignore quiz.Commenting.FunctionComment.IncorrectTypeHint
	private static function getApplicationTester( string $className = TestCommand::class ): array {
		$tester = new ApplicationTester( $app = new Application() );

		$app->setDispatcher( $dispatcher = new EventDispatcher() );
		$app->setAutoExit( false );
		$app->setCatchExceptions( false );
		$dispatcher->addSubscriber( new CommandSubscriber() );

		$app->add( $command = $className::start( infer: false )->setName( 'test:command' ) );

		return array( $tester, $command, $app );
	}

	#[Test]
	public function itAddsExternalSubscribingMethod(): void {
		[$tester, $command] = $this->getApplicationTester();

		$command
			->addArgument( 'number', InputArgument::REQUIRED, 'no validate', suggestedValues: array( 'one', 2, 'three' ) )
			->setCode( static fn( InputInterface $i, OutputInterface $o ) => $o->write( $i->getArgument( 'number' ) . ' number' ) );

		CommandSubscriber::disableSuggestionValidation();

		$tester->run(
			$input = array(
				'command' => 'test:command',
				'number'  => 'not a suggested value but validation is suppressed',
			)
		);

		$tester->assertCommandIsSuccessful();

		CommandSubscriber::disableSuggestionValidation( false );

		// FIXME: not throwing exception as input attribute is not set.
		// phpcs:disable
		// $this->expectException( OutOfBoundsException::class );

		// $tester->run( $input );
		// phpcs:enable
	}

	#[Test]
	public function itSubscribesToTheSuggestedValues(): void {
		[$tester, $command] = $this->getApplicationTester();

		CommandSubscriber::disableSuggestionValidation( false );

		$command
			->addOption( 'option', default: 'nine', mode: InputOption::VALUE_REQUIRED, suggestedValues: array( 1, 'two', 'nine' ) )
			->setCode( static fn( InputInterface $i, OutputInterface $o ) => $o->write( $i->getOption( 'option' ) . ' option!' ) );

		$result = $tester->run(
			array(
				'command'  => 'test:command',
				'--option' => 1,
			)
		);

		$tester->assertCommandIsSuccessful();

		$this->assertSame( Console::SUCCESS, $result );
		$this->assertStringContainsString( $tester->getDisplay(), '1 option!' );

		$command
			->addArgument( 'number', InputArgument::OPTIONAL, 'no validate', suggestedValues: array( 'one', 2, 'three' ) )
			->addOption( 'option', default: 'nine', mode: InputOption::VALUE_REQUIRED, suggestedValues: array( 1, 'two', 'nine' ) )
			->setCode( static fn( InputInterface $i, OutputInterface $o ) => $o->write( $i->getArgument( 'number' ) . ' number' ) );

		$tester->run(
			array(
				'command' => 'test:command',
				'number'  => 'three',
			)
		);

		$tester->assertCommandIsSuccessful();
		$this->assertStringContainsString( 'three number', $tester->getDisplay() );

		// FIXME: not throwing exception as input attribute is not set.
		// phpcs:disable
		// $this->expectException( OutOfBoundsException::class );

		// $tester->run(
		// 	array(
		// 		'command'  => 'test:command',
		// 		'--option' => 2,
		// 	)
		// );
		// phpcs:enable
	}

	#[Test]
	public function itDoesNotValidateSuggestedValuesBasedOnAttribute(): void {
		[$tester, $command] = $this->getApplicationTester( TestNoValidate::class );

		$command
			->addArgument( 'number', InputArgument::REQUIRED, 'no validate', suggestedValues: array( 'one', 2, 'three' ) )
			->setCode( static fn( InputInterface $i, OutputInterface $o ) => $o->write( $i->getArgument( 'number' ) ) );

		$tester->run(
			array(
				'command' => 'test:command',
				'number'  => 'invalid but will not be validated',
			)
		);

		$tester->assertCommandIsSuccessful();
		$this->assertStringContainsString( 'invalid but will not be validated', $tester->getDisplay() );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

class TestCommand extends Console {}

#[DoNotValidateSuggestedValues]
class TestNoValidate extends Console {}
