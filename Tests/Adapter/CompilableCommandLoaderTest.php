<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Adapter;

use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Cli\Cli;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Test\Fixture\TableConsoleCommand;
use TheWebSolver\Codegarage\Cli\Adapter\CompilableCommandLoader;

class CompilableCommandLoaderTest extends TestCase {
	#[Test]
	public function createsCompilableCommandsForIsoPackage(): void {
		$fixtureDir    = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'Fixture';
		$compiledArray = CompilableCommandLoader::start()
			->inDirectory( $fixtureDir, 'TheWebSolver\Codegarage\Test\Fixture' )
			->load( $this->getContainerMock() )
			->getArrayFile()->getContent();

		$this->assertSame( [ TableConsoleCommand::class, 'start' ], $compiledArray[ TableConsoleCommand::class ] );
	}

	private function getContainerMock(): ContainerInterface {
		( $container = $this->createMock( ContainerInterface::class ) )
			->method( 'get' )
			->with( Cli::class )
			->willReturn( $this->createStub( Cli::class ) );

		return $container;
	}
}
