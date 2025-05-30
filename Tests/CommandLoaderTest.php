<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Cli\Cli;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Container;
use TheWebSolver\Codegarage\Test\Scan\Valid;
use TheWebSolver\Codegarage\Cli\CommandLoader;
use TheWebSolver\Codegarage\Cli\Data\EventTask;
use TheWebSolver\Codegarage\Test\Stub\TestCommand;
use TheWebSolver\Codegarage\Cli\Traits\SubDirectoryAware;
use TheWebSolver\Codegarage\Test\Stub\AnotherScannedCommand;
use TheWebSolver\Codegarage\Test\Stub\SubStub\FirstDepthCommand;

class CommandLoaderTest extends TestCase {
	private const LOCATION       = [ DirectoryScannerTest::STUB_PATH, __NAMESPACE__ . '\\Stub' ];
	private const NAMESPACED_DIR = [ self::LOCATION[1] => self::LOCATION[0] ];

	protected function setUp(): void {
		require_once Cli::ROOT . 'bootstrap.php';
	}

	private const EXPECTED_FILENAMES = [ 'TestCommand', 'AnotherScannedCommand' ];
	private const EXPECTED_COMMANDS  = [
		'app:testCommand'               => TestCommand::class,
		'scanned:anotherScannedCommand' => AnotherScannedCommand::class,
	];

	#[Test]
	public function itScansAndLazyloadCommandFromGivenLocation(): void {
		$loader = CommandLoader::loadCommands( [ self::NAMESPACED_DIR ], new Container() );

		$this->assertEmpty( array_diff_key( self::EXPECTED_COMMANDS, $loader->getCommands() ) );
		$this->assertEmpty( array_diff( self::EXPECTED_FILENAMES, $loader->getScannedItems() ) );
	}

	#[Test]
	public function itListensForEventsForEachResolvedCommandFile(): void {
		$loader = CommandLoader::withEvent( $this->assertLoadedCommandIsListened( ... ) )
			->inDirectory( ...self::LOCATION )
			->load( new Container() );

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
		CommandLoader::loadCommands( [ self::NAMESPACED_DIR ], $container = new Container() );

		foreach ( self::EXPECTED_COMMANDS as $class ) {
			// But once the command is resolved by container, it becomes a singleton.
			$this->assertSame( $container->get( $class ), $container->get( $class ) );
		}
	}

	#[Test]
	public function itProvidesLazyLoadedCommandsToCli(): void {
		$container = new Container();

		$container->setShared( Cli::class );

		CommandLoader::loadCommands( [ self::NAMESPACED_DIR ], $container );

		$cli = $container->get( Cli::class );

		$this->assertCount( 1, $cli->all( 'app' ) );
		$this->assertCount( 1, $cli->all( 'scanned' ) );

		foreach ( self::EXPECTED_COMMANDS as $name => $class ) {
			$this->assertInstanceOf( $class, $cli->get( $name ) );
		}
	}

	#[Test]
	public function itRegistersCommandsFromSubDirectories(): void {
		$subDirLoader = SubDirectoryAwareLoader::start()
			->usingSubDirectory( 'SubStub', 2 )
			->inDirectory( ...self::LOCATION )
			->load( new Container() );

		$this->assertContains( FirstDepthCommand::class, $subDirLoader->getCommands() );
		$this->assertCount( 4, $subDirLoader->getScannedItems() );

		$subDirLoader = SubDirectoryAwareLoader::start()
			->inDirectory( DirectoryScannerTest::SCAN_PATH, __NAMESPACE__ . '\\Scan' )
			->usingSubDirectory( 'SubStub', 2 )
			->inDirectory( ...self::LOCATION )
			->load( new Container() );

		$this->assertContains( Valid::class, $subDirLoader->getCommands() );
		$this->assertCount( 6, $subDirLoader->getScannedItems() );
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
class SubDirectoryAwareLoader extends CommandLoader {
	use SubDirectoryAware;
}
