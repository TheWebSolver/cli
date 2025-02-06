<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use SplFileInfo;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Traits\DirectoryScanner;
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
	public function itUsesScannedItemAwareToGetScannedItems(): void {
		$scanner = new class() {
			use DirectoryScanner, ScannedItemAware, SubDirectoryAware;

			/** @return string[] */
			protected function getAllowedExtensions(): array {
				return array( 'php', 'gitkeep' );
			}

			private string $currentRoot;

			protected function getRootPath(): string {
				return $this->currentRoot;
			}

			public function run(): self {
				foreach ( array( DirectoryScannerTest::SCAN_PATH, DirectoryScannerTest::STUB_PATH ) as $root ) {
					$this->currentRoot = $root;

					$this->scan( $root );
				}

				return $this;
			}

			protected function forCurrentFile(): void {}
		};

		$scanner->usingSubDirectory( 'SubStub', 2, 3 )->run();

		$this->assertCount(
			6,
			$scanner->getScannedItems(),
			'Does not include "SubStub" from parent "FirstDepth" coz it is not scanned.'
		);

		$scanner = new $scanner();

		$scanner->usingSubDirectory( 'SubStub', 2, 3 )->usingSubDirectory( 'FirstDepth', 2 )->run();

		$this->assertCount( 9, $scanner->getScannedItems(), 'Includes "SubStub" from parent "FirstDepth"' );
		$this->assertCount( 2, $depths = $scanner->getScannedItemsDepth() );
		$this->assertCount( 3, $scanDir = $depths['Scan'] );
		$this->assertCount( 8, $stubDir = $depths['Stub'] );

		$this->assertCount( 1, $scanRootDirDepth = array_filter( $scanDir, fn( $details ) => 1 === $details['depth'] ) );

		$scanDirItem = $scanRootDirDepth[0];

		$this->assertEmpty( $scanDirItem['tree'] );
		$this->assertSame( 'Scan', $scanDirItem['base'] );
		$this->assertTrue( $scanDirItem['item']->isDir() );
		$this->assertSame( 'directory', $scanDirItem['type'] );

		$this->assertCount( 1, $stubRootDirDepth = array_filter( $stubDir, fn( $details ) => 1 === $details['depth'] ) );

		$stubDirItem = $stubRootDirDepth[0];

		$this->assertEmpty( $stubDirItem['tree'] );
		$this->assertTrue( $stubDirItem['item']->isDir() );
		$this->assertSame( 'Stub', $stubDirItem['base'] );
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

		$this->assertIsArray( $item );
		$this->assertSame( 'file', $item['type'] );
		$this->assertSame( '.gitkeep', $item['base'] );
		$this->assertTrue( $item['item']->isFile() );
		$this->assertSame( array( 'Stub', 'FirstDepth', 'SubStub' ), $item['tree'] );

		$this->assertSame( 4, $scanner->getMaxDepth() );

		$onlyDepths = array_column( $scanner->getScannedItemsDepth( true )['Stub'], 'depth' );
		$depths     = $onlyDepths;
		sort( $onlyDepths );

		$this->assertSame( $depths, $onlyDepths );

		$onlyDepths = array_column( $scanner->getScannedItemsDepth()['Stub'], 'depth' );
		$depths     = $onlyDepths;
		sort( $onlyDepths );

		$this->assertNotSame( $depths, $onlyDepths );
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

	protected function shouldScanFile( SplFileInfo $info ): bool {
		return ! str_contains( $info->getBasename(), needle: 'Ignore' );
	}

	protected function forCurrentFile(): void {}
}
