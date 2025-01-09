<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Cli\Cli;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\CommandLoader;
use TheWebSolver\Codegarage\Test\Stub\TestCommand;
use TheWebSolver\Codegarage\Cli\Event\BeforeRunEvent;

class CommandLoaderTest extends TestCase {
	#[Test]
	public function itRunsCommandUsingEventListener(): void {
		$dispatcher = Cli::app()->eventDispatcher();

		$dispatcher->addListener(
			BeforeRunEvent::class,
			static fn( BeforeRunEvent $e ) => $e->runCommand( self::assertCommandIsLazyLoaded( ... ) )
		);

		$dispatcher->addListener(
			BeforeRunEvent::class,
			static fn( BeforeRunEvent $e ) => $e->runCommand( self::assertCommandFileIsScanned( ... ) )
		);

		$dir    = 'Tests' . DIRECTORY_SEPARATOR . 'Stub';
		$loader = CommandLoader::run( directory: Cli::ROOT . $dir, ns: __NAMESPACE__ . '\\Stub' );

		$this->assertCount( 1, $loader->getClassNames() );
	}

	public static function assertCommandIsLazyLoaded( CommandLoader $loader ): void {
		self::assertInstanceOf( TestCommand::class, ( $loader->getLazyLoadedCommands()['app:testCommand'] )() );
	}

	public static function assertCommandFileIsScanned( CommandLoader $loader ): void {
		self::assertArrayHasKey( 'TestCommand', $loader->getFileNames() );
	}
}
