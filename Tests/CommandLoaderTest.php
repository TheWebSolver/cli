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
use TheWebSolver\Codegarage\Test\Stub\SubStub\FirstDepthCommand;

class CommandLoaderTest extends TestCase {
	private const LOCATION = array(
		Cli::ROOT . 'Tests' . DIRECTORY_SEPARATOR . 'Stub',
		__NAMESPACE__ . '\\Stub',
	);

	private const EXPECTED_FILENAMES = array( 'TestCommand', 'AnotherScannedCommand' );
	private const EXPECTED_COMMANDS  = array(
		'app:testCommand'               => TestCommand::class,
		'scanned:anotherScannedCommand' => AnotherScannedCommand::class,
	);

	#[Test]
	public function itScansAndLazyloadCommandFromGivenLocation(): void {
		$loader = CommandLoader::load( array( self::LOCATION ) );

		$this->assertEmpty( array_diff_key( self::EXPECTED_COMMANDS, $loader->getCommands() ) );
		$this->assertEmpty( array_diff( self::EXPECTED_FILENAMES, $loader->getScannedItems() ) );
	}

	#[Test]
	public function itListensForEventsForEachResolvedCommandFile(): void {
		$loader = CommandLoader::subscribe()
			->forLocation( self::LOCATION )
			->withListener( $this->assertLoadedCommandIsListened( ... ) );

		$this->assertCount( 2, $fileNames = $loader->getScannedItems() );
		$this->assertEmpty( array_diff( self::EXPECTED_FILENAMES, $fileNames ) );
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
		$loader = CommandLoader::load( array( self::LOCATION ), new Container() );

		foreach ( self::EXPECTED_COMMANDS as $class ) {
			// The command is registered to container as a closure by CommandLoader.
			$this->assertEquals( $class::start( ... ), $loader->getContainer()->getBinding( $class )->material );
			// But once the command is resolved by container, it becomes a singleton.
			$this->assertSame( $loader->getContainer()->get( $class ), $loader->getContainer()->get( $class ) );
		}
	}

	#[Test]
	public function itProvidesLazyLoadedCommandsToCli(): void {
		$loader = CommandLoader::load( array( self::LOCATION ) );
		$cli    = $loader->getContainer()->get( Cli::class );

		$this->assertCount( 1, $cli->all( 'app' ) );
		$this->assertCount( 1, $cli->all( 'scanned' ) );

		foreach ( self::EXPECTED_COMMANDS as $name => $class ) {
			$this->assertInstanceOf( $class, $cli->get( $name ) );
		}
	}

	#[Test]
	public function itEnsuresCommandLoaderIsInstantiatedWithContainer(): void {
		$loader = CommandLoader::load( array( self::LOCATION ), $c = new Container() );

		$this->assertSame( $c, $loader->getContainer() );
	}

	#[Test]
	public function itRegistersCommandsFromSubDirectories(): void {
		$loader = CommandLoader::withSubDirectories( array( 'SubStub' => 1 ) )
			->forLocation( self::LOCATION )
			->scan();

		$this->assertCount( 2, $loader->getDirectoryNamespaceMap() );
		$this->assertContains( FirstDepthCommand::class, $loader->getCommands() );

		$loader = CommandLoader::withSubDirectories( array( 'SubStub' => array( 1, 2 ) ) )
			->forLocation( self::LOCATION )
			->scan();

		$this->assertCount( 2, $loader->getDirectoryNamespaceMap(), 'Must not scan sub-dir if parent-dir is ignored' );

		$depths = array(
			'SubStub'    => array( 1, 2 ),
			'FirstDepth' => 1,
		);

		$loader = CommandLoader::withSubdirectories( $depths )->forLocation( self::LOCATION )->scan();

		$this->assertCount( 4, $loader->getDirectoryNamespaceMap() );
	}
}
