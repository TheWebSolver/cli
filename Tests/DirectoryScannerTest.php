<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\DirectoryScanner;

class DirectoryScannerTest extends TestCase {
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
				return $this->realDirectoryPath( CommandLoaderTest::STUB_PATH );
			}

			protected function execute(): void {
				$item = $this->currentItem();

				if ( $item->valid() && $item->getFilename() === 'SubStub' ) {
					$this->scanDirectory();
				}
			}
		};

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage(
			sprintf(
				'Class "%1$s" must implement "%2$s::scanDirectory" method to scan "%3$s" directory',
				$scanner::class,
				DirectoryScanner::class,
				'SubStub'
			)
		);

		$scanner->run();
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
		return CommandLoaderTest::SCAN_PATH;
	}

	protected function currentItemIsIgnored(): bool {
		return ! $this->isScannableFile() || str_contains( $this->currentItem()->getBasename(), 'Ignore' );
	}

	protected function execute(): void {}
}
