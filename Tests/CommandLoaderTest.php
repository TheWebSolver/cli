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
	private const TEST_PATH = Cli::ROOT . 'Tests' . DIRECTORY_SEPARATOR;
	public const SCAN_PATH  = self::TEST_PATH . 'Scan' . DIRECTORY_SEPARATOR;
	public const STUB_PATH  = self::TEST_PATH . 'Stub' . DIRECTORY_SEPARATOR;
	private const LOCATION  = array(
		self::STUB_PATH,
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
		$loader = CommandLoader::subscribeWith( $this->assertLoadedCommandIsListened( ... ) )
			->inDirectory( self::LOCATION )
			->loadCommands();

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
			->inDirectory( self::LOCATION )
			->loadCommands();

		$this->assertCount( 2, $loader );
		$this->assertCount( 4, $loader->getScannedItems() );
		$this->assertContains( FirstDepthCommand::class, $loader->getCommands() );

		$loader = CommandLoader::withSubDirectories( array( 'SubStub' => array( 1, 2 ) ) )
			->inDirectory( self::LOCATION )
			->loadCommands();

		$this->assertCount( 2, $loader );
		$this->assertCount( 4, $loader->getScannedItems(), 'Must not scan sub-dir if parent-dir is ignored' );
	}

	#[Test]
	public function itRegistersNestedSubDirectoriesWithSameName(): void {
		$depths = array(
			'SubStub'    => array( 1, 2 ),
			'FirstDepth' => 1,
		);

		$loader = CommandLoader::withSubdirectories( $depths )
			->inDirectory( self::LOCATION, $scandir = array( self::SCAN_PATH, Valid::NAMESPACE ) )
			->loadCommands();

		$scannedSubDirectories = array(
			self::STUB_PATH . 'SubStub'    => 'SubStub',
			self::STUB_PATH . 'FirstDepth' => 'FirstDepth',
			self::STUB_PATH . 'FirstDepth' . DIRECTORY_SEPARATOR . 'SubStub' => 'SubStub',
		);

		$scannedItems = array(
			...$scannedSubDirectories,
			self::SCAN_PATH . 'Valid.php'  => 'Valid',
			self::SCAN_PATH . 'Ignore.php' => 'Ignore', // Scanned but not a command class file.
			self::STUB_PATH . self::EXPECTED_FILENAMES[0] . '.php' => self::EXPECTED_FILENAMES[0],
			self::STUB_PATH . self::EXPECTED_FILENAMES[1] . '.php' => self::EXPECTED_FILENAMES[1],
			self::STUB_PATH . 'SubStub' . DIRECTORY_SEPARATOR . 'FirstDepthCommand.php' => 'FirstDepthCommand',
		);

		$scannedDirectories = array_map(
			static fn( string $dirpath ) => realpath( $dirpath ),
			array( self::STUB_PATH, self::SCAN_PATH, ...( array_keys( $scannedSubDirectories ) ) )
		);

		$this->assertCount( 5, $loader );
		$this->assertEmpty( array_diff( $scannedDirectories, $loader->getScannedDirectories() ) );

		foreach ( array( self::LOCATION, $scandir ) as $map ) {
			$this->assertContains( $map, $loader->getDirectoryNamespaceMap() );
		}

		$this->assertEmpty( array_diff( $scannedItems, $loader->getScannedItems() ) );

		$scannedItemPaths = array_flip(
			array_map( static fn( $p ) => realpath( $p ), array_flip( $scannedItems ) )
		);

		$this->assertEmpty( array_diff_key( $scannedItemPaths, $loader->getScannedItems() ) );

		$commands = array( Valid::class, FirstDepthCommand::class, ...self::EXPECTED_COMMANDS );

		$this->assertEmpty( array_diff( $commands, $loader->getCommands() ) );
	}
}
