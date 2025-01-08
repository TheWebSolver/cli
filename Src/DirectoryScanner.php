<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use DirectoryIterator;

trait DirectoryScanner {
	public const EXTENSION = 'php';

	/** @var array<string,string> */
	protected array $scannedFiles;

	abstract protected function isIgnored( string $filename ): bool;

	abstract protected function executeFor( string $filename, string $filepath ): void;

	private function scan( string $directory ): self {
		foreach ( new DirectoryIterator( $directory ) as $item ) {
			if ( ! $this->isPHPFile( $item ) ) {
				continue;
			}

			if ( $this->isIgnored( $filename = $item->getBasename( '.' . self::EXTENSION ) ) ) {
				continue;
			}

			$this->scannedFiles[ $filename ] = $pathname = $item->getPathname();

			$this->executeFor( $filename, $pathname );
		}

		return $this;
	}

	private function isPHPFile( DirectoryIterator $current ): bool {
		return ! $current->isDot() && $current->isFile() && $current->getExtension() === self::EXTENSION;
	}
}
