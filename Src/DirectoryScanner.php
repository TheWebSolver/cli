<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use DirectoryIterator;

trait DirectoryScanner {
	public const EXTENSION = 'php';

	/** @var array<string,string> List of found filenames indexed by filePath. */
	protected array $scannedFiles;

	abstract protected function executeFor( string $filename, string $filepath ): void;

	protected function isIgnored( DirectoryIterator $item ): bool {
		return ! $this->isPHPFile( $item );
	}


	private function scan( string $directory ): static {
		foreach ( new DirectoryIterator( $directory ) as $item ) {
			if ( $item->isDot() ) {
				continue;
			}

			if ( $this->isIgnored( $item ) ) {
				continue;
			}

			$filename                        = $item->getBasename( '.' . self::EXTENSION );
			$pathname                        = $item->getPathname();
			$this->scannedFiles[ $pathname ] = $filename;

			$this->executeFor( $filename, $pathname );
		}

		return $this;
	}

	protected function isPHPFile( DirectoryIterator $current ): bool {
		return $current->isFile() && $current->getExtension() === self::EXTENSION;
	}
}
