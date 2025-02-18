<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Adapter;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Cli\Cli;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Iso\Cli\Iso3166Command;
use TheWebSolver\Codegarage\Iso\Cli\Iso4217Command;
use TheWebSolver\Codegarage\Cli\Adapter\CompilableCommandLoader;

class CompilableCommandLoaderTest extends TestCase {
	#[Test]
	public function createsCompilableCommandsForIsoPackage(): void {
		$isoCli = InstalledVersions::getInstallPath( 'thewebsolver/iso-cli' );
		$loader = CompilableCommandLoader::start()
			->inDirectory( "{$isoCli}/Src", 'TheWebSolver\Codegarage\Iso\Cli' )
			->load( $this->getContainerMock() );

		$this->assertArrayHasKey( Iso3166Command::class, $loader->getArrayFile()->getContent() );
		$this->assertArrayHasKey( Iso4217Command::class, $loader->getArrayFile()->getContent() );
	}

	private function getContainerMock(): ContainerInterface {
		( $container = $this->createMock( Container::class ) )
			->method( 'get' )
			->with( Cli::class )
			->willReturn( $this->createStub( Cli::class ) );

		return $container;
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

class Container implements ContainerInterface {
	public function set( string $id ): void {}
	public function get( string $id ): mixed {
		return null;
	}
	public function has( string $id ): bool {
		return false;
	}
}
