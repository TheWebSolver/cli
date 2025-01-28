<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\DirectoryScanner;

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
