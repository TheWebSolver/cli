<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;
use TheWebSolver\Codegarage\Cli\DirectoryScanner;
use TheWebSolver\Codegarage\Cli\Traits\ScannedItemAware;

class DirectoryScannerTest extends TestCase {
	public const TEST_PATH = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Tests' . DIRECTORY_SEPARATOR;
	public const SCAN_PATH = self::TEST_PATH . 'Scan' . DIRECTORY_SEPARATOR;
	public const STUB_PATH = self::TEST_PATH . 'Stub' . DIRECTORY_SEPARATOR;

	#[Test]
	public function itScansCurrentDirectoryFiles(): void {
		$scanner = new Scanner();
		$scanner->run();

		$this->assertCount( 1, $files = $scanner->getScannedItems() );
		$this->assertContains( 'Valid', $files );
	}

	#[Test]
	public function itForbidsUsingScannedDirMethodWithoutImplementation(): void {
		$scanner = new class() {
			use DirectoryScanner;

			public function run(): self {
				$this->subDirectories = array( 'SubStub' => 1 );

				$this->scan( $this->getRootPath() );

				return $this;
			}

			protected function getRootPath(): string {
				return $this->realDirectoryPath( DirectoryScannerTest::STUB_PATH );
			}

			protected function forCurrentFile(): void {}
		};

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage(
			sprintf(
				'Class "%1$s" must implement "%2$s::forCurrentSubDirectory" method to scan "%3$s" sub-directory',
				$scanner::class,
				DirectoryScanner::class,
				'SubStub'
			)
		);

		$scanner->run();
	}

	#[Test]
	public function itUsesScannedItemAwareToGetScannedItems(): object {
		$scanner = new class() {
			use DirectoryScanner, ScannedItemAware {
				DirectoryScanner::usingSubDirectories as public;
			}

			protected function getAllowedExtensions(): array {
				return array( 'php', 'gitkeep' );
			}

			private string $currentRoot;

			protected function getRootPath(): string {
				return $this->realDirectoryPath( $this->currentRoot );
			}

			public function run(): self {
				foreach ( array( DirectoryScannerTest::SCAN_PATH, DirectoryScannerTest::STUB_PATH ) as $root ) {
					$this->currentRoot = $root;

					$this->scan( $root );
				}

				return $this;
			}

			protected function forCurrentFile(): void {}
			protected function forCurrentSubDirectory(): void {
				$this->scan( $this->currentItem()->getRealPath() );
			}
		};

		$subDirectories = array(
			'SubStub'    => array( 1, 2 ),
			'FirstDepth' => 1,
		);

		$scanner->usingSubDirectories( $subDirectories )->run();

		$this->assertCount( 2, $depths = $scanner->getScannedItemsDepth() );
		$this->assertCount( 2, $scanDir = $depths['Scan'] );
		$this->assertCount( 7, $stubDir = $depths['Stub'] );

		$scanDirFiles = array( 'Valid.php', 'Ignore.php' );

		foreach ( $scanDir as $details ) {
			$this->assertSame( 0, $details['depth'] );
			$this->assertTrue( ( $item = $details['item'] )->isFile() );
			$this->assertContains( $item->getBasename(), $scanDirFiles );
			$this->assertSame( array( 'Scan' ), $details['tree'] );
		}

		$noDepthFileNames = array( 'TestCommand.php', 'AnotherScannedCommand.php' );
		$zeroDepths       = array_filter( $stubDir, fn( $details ) => 0 === $details['depth'] );

		$this->assertCount( 2, $zeroDepths );

		foreach ( $zeroDepths as $file ) {
			$this->assertContains( $file['item']->getBasename(), $noDepthFileNames );
			$this->assertSame( array( 'Stub' ), $file['tree'] );
		}

		$firstDepths         = array_filter( $stubDir, fn( $details ) => 1 === $details['depth'] );
		$firstDepthFilesDirs = array( 'FirstDepth', 'SubStub', 'FirstDepthCommand.php' );

		$this->assertCount( 3, $firstDepths );

		foreach ( $firstDepths as $firstDepthDetails ) {
			$this->assertContains( $firstDepthDetails['item']->getBasename(), $firstDepthFilesDirs );

			if ( 'file' === $firstDepthDetails['type'] ) {
				$this->assertSame( array( 'Stub', 'SubStub' ), $firstDepthDetails['tree'] );
			} else {
				$this->assertSame( array( 'Stub' ), $firstDepthDetails['tree'] );
			}
		}

		$secondDepthDir      = array_filter( $stubDir, fn( $details ) => 2 === $details['depth'] );
		$secondDepthDirFiles = array( 'SubStub', '.gitkeep' );

		$this->assertCount( 2, $secondDepthDir );

		foreach ( $secondDepthDir as $secondDepthDetails ) {
			$this->assertContains( $secondDepthDetails['item']->getBasename(), $secondDepthDirFiles );

			if ( 'file' === $secondDepthDetails['type'] ) {
				$this->assertSame( array( 'Stub', 'FirstDepth', 'SubStub' ), $secondDepthDetails['tree'] );
			} else {
				$this->assertSame( array( 'Stub', 'FirstDepth' ), $secondDepthDetails['tree'] );
			}
		}

		return $scanner;
	}

	#[Test]
	#[Depends( 'itUsesScannedItemAwareToGetScannedItems' )]
	public function itEnsuresScannedDepthsAreSorted( object $scanner ): void {
		$onlyDepths = array_column( $scanner->getScannedItemsDepth()['Stub'], 'depth' );
		$depths     = array_values( $onlyDepths );
		sort( $onlyDepths );

		$this->assertNotSame( $depths, $onlyDepths );

		$onlyDepths = array_column( $scanner->getScannedItemsDepth( true )['Stub'], 'depth' );
		$depths     = array_values( $onlyDepths );
		sort( $onlyDepths );

		$this->assertSame( $depths, $onlyDepths );
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class Scanner {
	use DirectoryScanner;

	public function run(): self {
		$this->scan( $this->getRootPath() );

		return $this;
	}

	protected function getRootPath(): string {
		return DirectoryScannerTest::SCAN_PATH;
	}

	protected function shouldRegisterCurrentItem(): bool {
		$item = $this->currentItem();

		return ! $item->isDot()
			&& $this->currentItemIsFileWithAllowedExtension()
			&& ! str_contains( $item->getBasename(), 'Ignore' );
	}

	protected function forCurrentFile(): void {}
}
