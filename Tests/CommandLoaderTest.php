<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Cli\Cli;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Test\Scan\Valid;
use TheWebSolver\Codegarage\Cli\CommandLoader;
use TheWebSolver\Codegarage\Cli\Data\EventTask;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Test\Stub\TestCommand;
use TheWebSolver\Codegarage\Test\Stub\AnotherScannedCommand;
use TheWebSolver\Codegarage\Test\Stub\SubStub\FirstDepthCommand;

class CommandLoaderTest extends TestCase {
	private const LOCATION = array( __NAMESPACE__ . '\\Stub' => DirectoryScannerTest::STUB_PATH );

	protected function setUp(): void {
		require_once Cli::ROOT . 'bootstrap.php';
	}

	private const EXPECTED_FILENAMES = array( 'TestCommand', 'AnotherScannedCommand' );
	private const EXPECTED_COMMANDS  = array(
		'app:testCommand'               => TestCommand::class,
		'scanned:anotherScannedCommand' => AnotherScannedCommand::class,
	);

	#[Test]
	public function itScansAndLazyloadCommandFromGivenLocation(): void {
		$loader = CommandLoader::loadCommands( array( self::LOCATION ) );

		$this->assertEmpty( array_diff_key( self::EXPECTED_COMMANDS, $loader->getCommands() ) );
		$this->assertEmpty( array_diff( self::EXPECTED_FILENAMES, $loader->getScannedItems() ) );
	}

	#[Test]
	public function itListensForEventsForEachResolvedCommandFile(): void {
		$loader = CommandLoader::withEvent( $this->assertLoadedCommandIsListened( ... ) )
			->inDirectory( array( self::LOCATION ) )
			->load();

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
		$loader = CommandLoader::loadCommands( array( self::LOCATION ), new Container() );

		foreach ( self::EXPECTED_COMMANDS as $class ) {
			// The command is registered to container as a closure by CommandLoader.
			$this->assertEquals( $class::start( ... ), $loader->getContainer()->getBinding( $class )->material );
			// But once the command is resolved by container, it becomes a singleton.
			$this->assertSame( $loader->getContainer()->get( $class ), $loader->getContainer()->get( $class ) );
		}
	}

	#[Test]
	public function itProvidesLazyLoadedCommandsToCli(): void {
		$loader = CommandLoader::loadCommands( array( self::LOCATION ) );
		$cli    = $loader->getContainer()->get( Cli::class );

		$this->assertCount( 1, $cli->all( 'app' ) );
		$this->assertCount( 1, $cli->all( 'scanned' ) );

		foreach ( self::EXPECTED_COMMANDS as $name => $class ) {
			$this->assertInstanceOf( $class, $cli->get( $name ) );
		}
	}

	#[Test]
	public function itEnsuresCommandLoaderIsInstantiatedWithContainer(): void {
		$loader = CommandLoader::loadCommands( array( self::LOCATION ), $c = new Container() );

		$this->assertSame( $c, $loader->getContainer() );
	}

	#[Test]
	public function itRegistersCommandsFromSubDirectories(): void {
		$loader = CommandLoader::withSubDirectories( array( 'SubStub' => 1 ) )
			->inDirectory( array( self::LOCATION ) )
			->load();

		$this->assertCount( 2, $loader );
		$this->assertCount( 4, $loader->getScannedItems() );
		$this->assertContains( FirstDepthCommand::class, $loader->getCommands() );

		$loader = CommandLoader::withSubDirectories( array( 'SubStub' => array( 1, 2 ) ) )
			->inDirectory( array( self::LOCATION ) )
			->load();

		$this->assertCount( 2, $loader );
		$this->assertCount( 4, $loader->getScannedItems(), 'Must not scan sub-dir if parent-dir is ignored' );
	}

	#[Test]
	public function itRegistersNestedSubDirectoriesWithSameName(): void {
		$scanPath = DirectoryScannerTest::SCAN_PATH;
		$stubPath = DirectoryScannerTest::STUB_PATH;
		$depths   = array(
			'SubStub'    => array( 1, 2 ),
			'FirstDepth' => 1,
		);

		$loader = CommandLoader::withSubdirectories( $depths )
			->inDirectory( array( self::LOCATION, $scandir = array( Valid::NAMESPACE => $scanPath ) ) )
			->load();

		$scannedSubDirectories = array(
			$stubPath . 'SubStub'    => 'SubStub',
			$stubPath . 'FirstDepth' => 'FirstDepth',
			$stubPath . 'FirstDepth' . DIRECTORY_SEPARATOR . 'SubStub' => 'SubStub',
		);

		$expectedScanItems = array(
			...$scannedSubDirectories,
			$scanPath . 'Valid.php'  => 'Valid',
			$scanPath . 'Ignore.php' => 'Ignore', // Scanned but not a command class file.
			$stubPath . self::EXPECTED_FILENAMES[0] . '.php' => self::EXPECTED_FILENAMES[0],
			$stubPath . self::EXPECTED_FILENAMES[1] . '.php' => self::EXPECTED_FILENAMES[1],
			$stubPath . 'SubStub' . DIRECTORY_SEPARATOR . 'FirstDepthCommand.php' => 'FirstDepthCommand',
		);

		$expectedScanSubDirectories = array_map(
			static fn( string $dirpath ) => realpath( $dirpath ),
			array( $stubPath, $scanPath, ...( array_keys( $scannedSubDirectories ) ) )
		);

		$this->assertCount( 5, $loader );
		$this->assertEmpty( array_diff( $expectedScanSubDirectories, $loader->getScannedDirectories() ) );

		$this->assertCount( 2, $loader->getNamespacedDirectories() );

		foreach ( array( self::LOCATION, $scandir ) as $expectedRootPathAndItsNamespace ) {
			$this->assertContains( $expectedRootPathAndItsNamespace, $loader->getNamespacedDirectories() );
		}

		$this->assertCount( 8, $loader->getScannedItems() );
		$this->assertEmpty( array_diff( $expectedScanItems, $loader->getScannedItems() ) );

		$expectedScanItemPaths = array_flip(
			array_map( static fn( $p ) => realpath( $p ), array_flip( $expectedScanItems ) )
		);

		$this->assertEmpty( array_diff_key( $expectedScanItemPaths, $loader->getScannedItems() ) );

		$expectedCommandClasses = array( Valid::class, FirstDepthCommand::class, ...self::EXPECTED_COMMANDS );

		$this->assertCount( 4, $loader->getCommands() );
		$this->assertEmpty( array_diff( $expectedCommandClasses, $loader->getCommands() ) );
	}
}
