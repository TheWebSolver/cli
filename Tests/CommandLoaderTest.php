<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Cli\Cli;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\CommandLoader;
use TheWebSolver\Codegarage\Cli\Data\EventTask;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Test\Stub\TestCommand;
use TheWebSolver\Codegarage\Test\Stub\AnotherScannedCommand;

class CommandLoaderTest extends TestCase {
	private const LOCATION = array(
		Cli::ROOT . 'Tests' . DIRECTORY_SEPARATOR . 'Stub',
		__NAMESPACE__ . '\\Stub',
		null,
		false,
	);

	private const EXPECTED_FILENAMES = array( 'TestCommand', 'AnotherScannedCommand' );
	private const EXPECTED_COMMANDS  = array(
		'app:testCommand'               => TestCommand::class,
		'scanned:anotherScannedCommand' => AnotherScannedCommand::class,
	);

	#[Test]
	public function itScansAndLazyloadCommandFromGivenLocation(): void {
		$loader = CommandLoader::run( ...self::LOCATION );

		$this->assertEmpty( array_diff( self::EXPECTED_COMMANDS, $loader->getCommands() ) );
		$this->assertEmpty( array_diff_key( self::EXPECTED_COMMANDS, $loader->getCommands() ) );
		$this->assertEmpty( array_diff( self::EXPECTED_FILENAMES, array_keys( $loader->getFileNames() ) ) );
	}

	#[Test]
	public function itListensForEventsForEachResolvedCommandFile(): void {
		$loader = CommandLoader::subscribe()
			->toLocation( ...self::LOCATION )
			->withListener( $this->assertLoadedCommandIsListened( ... ) );

		$this->assertCount( 2, $fileNames = $loader->getFileNames() );
		$this->assertEmpty( array_diff( self::EXPECTED_FILENAMES, array_keys( $fileNames ) ) );
	}

	public function assertLoadedCommandIsListened( EventTask $task ): void {
		$this->assertContains( $task->className, self::EXPECTED_COMMANDS );
		$this->assertInstanceOf( self::EXPECTED_COMMANDS[ $task->commandName ], ( $task->command )() );
		$this->assertInstanceOf(
			$task->className,
			$task->container->get( $task->className ),
			'Console instance is return when using Container and Cli gets registered to the console.'
		);
	}

	#[Test]
	public function itEnsuresCommandsAreLazyLoadedToContainer(): void {
		$loader = CommandLoader::run( self::LOCATION[0], self::LOCATION[1], new Container(), false );
		$this->assertTrue( true );

		foreach ( self::EXPECTED_COMMANDS as $class ) {
			// The command is registered to container as a closure by CommandLoader.
			$this->assertEquals( $class::start( ... ), $loader->getContainer()->getBinding( $class )->material );
			// But once the command is resolved by container, it becomes a singleton.
			$this->assertSame( $loader->getContainer()->get( $class ), $loader->getContainer()->get( $class ) );
		}
	}

	#[Test]
	public function itProvidesLazyLoadedCommandsToCli(): void {
		$loader = CommandLoader::run( ...self::LOCATION );
		$cli    = $loader->getContainer()->get( Cli::class );

		$this->assertCount( 1, $cli->all( 'app' ) );
		$this->assertCount( 1, $cli->all( 'scanned' ) );

		foreach ( self::EXPECTED_COMMANDS as $name => $class ) {
			$this->assertInstanceOf( $class, $cli->get( $name ) );
		}
	}

	#[Test]
	public function itEnsuresCommandLoaderIsInstantiatedWithContainer(): void {
		$loader = CommandLoader::run( self::LOCATION[0], self::LOCATION[1], $c = new Container(), false );

		$this->assertSame( $c, $loader->getContainer() );
	}
}
