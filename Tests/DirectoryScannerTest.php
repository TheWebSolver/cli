<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use DirectoryIterator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\DirectoryScanner;

class DirectoryScannerTest extends TestCase {
	#[Test]
	public function itScansCurrentDirectoryFiles(): void {
		$scanner = new Scanner();
		$scanner->run();

		$this->assertCount( 1, $files = $scanner->getFiles() );
		$this->assertContains( 'Valid', $files );
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
		return __DIR__ . '/Scan';
	}

	protected function isIgnored(): bool {
		return ! $this->isPHPFile( $this->currentItem() )
			|| str_contains( $this->currentItem()->getBasename(), 'Ignore' );
	}

	public function getFiles(): array {
		return $this->scannedFiles;
	}

	protected function executeFor( string $filename, string $filePath ): void {}
}
