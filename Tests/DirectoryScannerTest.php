<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\DirectoryScanner;

class DirectoryScannerTest extends TestCase {
	#[Test]
	public function itScansCurrentDirectoryFiles(): void {
		$scanner = new Scanner();
		$scanner->run();

		$this->assertCount( 3, $files = $scanner->getFiles() );
		$this->assertArrayHasKey( substr( basename( __FILE__ ), 0, -4 /* remove .php */ ), $files );
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class Scanner {
	use DirectoryScanner;

	public function run(): self {
		$this->scan( __DIR__ );

		return $this;
	}

	protected function isIgnored( string $filename ): bool {
		return ! $filename;
	}

	public function getFiles(): array {
		return $this->scannedFiles;
	}

	protected function executeFor( string $filename, string $filePath ): void {}
}
