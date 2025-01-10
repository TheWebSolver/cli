<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
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
		$loader  = CommandLoader::run( ...self::LOCATION );
		$classes = array_map( fn( string $className ) => ltrim( $className, '\\' ), $loader->getClassNames() );

		$this->assertEmpty( array_diff( self::EXPECTED_COMMANDS, $classes ) );
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

	public function assertLoadedCommandIsListened( string $name, Closure $command, string $classname ): void {
		$this->assertContains( ltrim( $classname, '\\' ), self::EXPECTED_COMMANDS );
		$this->assertInstanceOf( self::EXPECTED_COMMANDS[ $name ], $command() );
	}
}
