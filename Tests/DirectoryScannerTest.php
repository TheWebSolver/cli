<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;
use TheWebSolver\Codegarage\Cli\DirectoryScanner;
use TheWebSolver\Codegarage\Cli\Traits\ScannedItemAware;
use TheWebSolver\Codegarage\Cli\Traits\SubDirectoryAware;

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
	public function itUsesScannedItemAwareToGetScannedItems(): object {
		$scanner = new class() {
			use DirectoryScanner, ScannedItemAware, SubDirectoryAware {
				SubDirectoryAware::usingSubDirectories as public;
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
		$this->assertCount( 3, $scanDir = $depths['Scan'] );
		$this->assertCount( 8, $stubDir = $depths['Stub'] );

		$this->assertCount( 1, $scanRootDirDepth = array_filter( $scanDir, fn( $details ) => 1 === $details['depth'] ) );

		$scanDirItem = reset( $scanRootDirDepth );

		$this->assertTrue( $scanDirItem['item']->isDir() );
		$this->assertEmpty( $scanDirItem['tree'] );
		$this->assertSame( 'directory', $scanDirItem['type'] );

		$this->assertCount( 1, $stubRootDirDepth = array_filter( $stubDir, fn( $details ) => 1 === $details['depth'] ) );

		$stubDirItem = reset( $stubRootDirDepth );

		$this->assertTrue( $stubDirItem['item']->isDir() );
		$this->assertEmpty( $stubDirItem['tree'] );
		$this->assertSame( 'directory', $stubDirItem['type'] );

		$this->assertCount( 4, $firstDepths = array_filter( $stubDir, fn( $details ) => 2 === $details['depth'] ) );

		foreach ( $firstDepths as $details ) {
			if ( 'directory' === $details['type'] ) {
				$this->assertTrue( $details['item']->isDir() );
				$this->assertContains( $details['base'], array( 'FirstDepth', 'SubStub' ) );
			} else {
				$this->assertTrue( $details['item']->isFile() );
				$this->assertContains( $details['base'], array( 'TestCommand.php', 'AnotherScannedCommand.php' ) );
			}

			$this->assertSame( array( 'Stub' ), $details['tree'] );
		}

		$this->assertCount( 2, $secondDepths = array_filter( $stubDir, fn( $details ) => 3 === $details['depth'] ) );

		foreach ( $secondDepths as $details ) {
			if ( 'directory' === $details['type'] ) {
				$this->assertTrue( $details['item']->isDir() );
				$this->assertSame( array( 'Stub', 'FirstDepth' ), $details['tree'] );
				$this->assertSame( 'SubStub', $details['base'] );
			} else {
				$this->assertTrue( $details['item']->isFile() );
				$this->assertSame( array( 'Stub', 'SubStub' ), $details['tree'] );
				$this->assertSame( 'FirstDepthCommand.php', $details['base'] );
			}
		}

		$this->assertCount( 1, $thirdDepths = array_filter( $stubDir, fn( $details ) => 4 === $details['depth'] ) );

		$item = reset( $thirdDepths );

		$this->assertSame( 'file', $item['type'] );
		$this->assertSame( '.gitkeep', $item['base'] );
		$this->assertTrue( $item['item']->isFile() );
		$this->assertSame( array( 'Stub', 'FirstDepth', 'SubStub' ), $item['tree'] );

		$this->assertSame( 4, $scanner->getMaxDepth() );

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
