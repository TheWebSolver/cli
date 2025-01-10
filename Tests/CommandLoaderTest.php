<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Cli\Cli;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\CommandLoader;
use TheWebSolver\Codegarage\Test\Stub\TestCommand;
use TheWebSolver\Codegarage\Test\Stub\AnotherScannedCommand;

class CommandLoaderTest extends TestCase {
	private const LOCATION = array(
		Cli::ROOT . 'Tests' . DIRECTORY_SEPARATOR . 'Stub',
		__NAMESPACE__ . '\\Stub',
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

		$this->assertEmpty( array_diff( self::EXPECTED_COMMANDS, $loader->getClassNames() ) );
		$this->assertEmpty( array_diff( self::EXPECTED_FILENAMES, array_keys( $loader->getFileNames() ) ) );

		$this->assertEmpty( array_diff_key( self::EXPECTED_COMMANDS, $loader->getLazyLoadedCommands() ) );

		foreach ( self::EXPECTED_COMMANDS as $commandName => $className ) {
			$this->assertInstanceOf( $className, $loader->getLazyLoadedCommands()[ $commandName ]() );
		}
	}

	#[Test]
	public function itListensForEventsForEachResolvedCommandFile(): void {
		$loader = CommandLoader::subscribe()
			->toLocation( ...self::LOCATION )
			->withListener( $this->assertLoadedCommandIsListened( ... ) );

		$this->assertCount( 2, $fileNames = $loader->getFileNames() );
		$this->assertEmpty( array_diff( self::EXPECTED_FILENAMES, array_keys( $fileNames ) ) );
	}

	public function assertLoadedCommandIsListened( string $name, callable $command, string $classname ): void {
		$this->assertContains( $classname, self::EXPECTED_COMMANDS );
		$this->assertInstanceOf( self::EXPECTED_COMMANDS[ $name ], $command() );
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
}
