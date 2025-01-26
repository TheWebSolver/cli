<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use DirectoryIterator;

trait DirectoryScanner {
	public const EXTENSION = 'php';

	/** @var array<string,int|int[]> */
	protected array $subDirectories = array();
	/** @var ?array{0:int,1:string} */
	private ?array $currentDepth = null;
	/** @var array<string,string> List of found filenames indexed by filePath. */
	protected array $scannedFiles;

	abstract protected function getRootPath(): string;
	abstract protected function executeFor( string $filename, string $filepath ): void;

	/** @param array<string,int|int[]> $nameWithDepth */
	protected function usingDirectories( array $nameWithDepth ): static {
		$this->subDirectories = $nameWithDepth;

		return $this;
	}

	/**
	 * Exposes scannable directory which can used to perform the recursive scanning.
	 * This method is invoked inside `static::isIgnored()` by validating with the
	 * user provided sub-directories using `static::usingDirectories()` method.
	 * Inside this method, among others, may perform the next scanning batch
	 * using provided directory name: `static::scan($item->getPathname())`.
	 */
	protected function scannableDirectory( DirectoryIterator $item ): void {}

	protected function isIgnored( DirectoryIterator $item ): bool {
		if ( $this->isPHPFile( $item ) ) {
			return false;
		}

		if ( ! $this->isScannableDir( $item ) ) {
			return true;
		}

		if ( ! $this->inCurrentDepthOf( $item )->directoryExists() ) {
			return true;
		}

		$this->scannableDirectory( $item );

		return false;
	}

	protected function isPHPFile( DirectoryIterator $current ): bool {
		return $current->isFile() && $current->getExtension() === self::EXTENSION;
	}

	protected function isScannableDir( DirectoryIterator $item ): bool {
		return $item->isDir() && ! empty( $this->subDirectories );
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

	private function inCurrentDepthOf( DirectoryIterator $item ): self {
		$pathname           = $item->getFileInfo()->getPathname();
		$subpath            = ltrim( substr( $pathname, strlen( $this->getRootPath() ) ), DIRECTORY_SEPARATOR );
		$this->currentDepth = array(
			count( explode( separator: DIRECTORY_SEPARATOR, string: $subpath ) ),
			$item->getFilename(),
		);

		return $this;
	}

	private function directoryExists(): bool {
		if ( ! $this->currentDepth ) {
			return false;
		}

		[$depth, $name]     = $this->currentDepth;
		$this->currentDepth = null;

		return in_array( $depth, (array) ( $this->subDirectories[ $name ] ?? array() ), strict: true );
	}
}
