<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use LogicException;
use DirectoryIterator;

trait DirectoryScanner {
	/** @var string */
	public const EXTENSION = 'php';

	private DirectoryIterator $currentlyScannedItem;
	/** @var string[] */
	private array $scannedDirectories = array();
	/** @var array<string,int|int[]> */
	private array $subDirectories = array();
	/** @var ?array{0:int,1:string} */
	private ?array $currentDepth = null;
	/** @var array<string,string> */
	private array $scannedPaths;

	final public function count(): int {
		return count( $this->scannedDirectories );
	}

	/** @return string[] List of scanned directory/sub-directory paths. */
	final public function getScannedDirectories(): array {
		return $this->scannedDirectories;
	}

	/** @return array<string,string> List of found file/directory name indexed by its realpath. */
	final public function getScannedItems(): array {
		return $this->scannedPaths;
	}

	/**
	 * Gets root directory path of the sub-directory currently being scanned.
	 * If no sub-directory is provided using `self::usingSubDirectories()`,
	 * it must return same value as the current directory being scanned.
	 */
	abstract protected function getRootPath(): string;

	/**
	 * Allows concrete to execute task for the non-ignored filenames inside scanned directory.
	 * Currently scanned item may be accessed within using `static::currentItem()` method.
	 */
	abstract protected function execute(): void;

	/**
	 * @param array<string,int|int[]> $nameWithDepth Sub-directory name and its depth (depths if same
	 *                                               name exists in nested directory) to be scanned.
	 */
	final protected function usingSubDirectories( array $nameWithDepth ): static {
		$this->subDirectories = $nameWithDepth;

		return $this;
	}

	final protected function currentItem(): DirectoryIterator {
		return $this->currentlyScannedItem;
	}

	/** Validates current file should be scanned if file extension is `static::EXTENSION`. */
	final protected function isScannableFile(): bool {
		return ( $file = $this->currentItem() )->isFile() && $file->getExtension() === static::EXTENSION;
	}

	/**
	 * Provides a template method which may be used to perform recursive scanning.
	 * This method is invoked inside `static::currentItemIsIgnored()` when user
	 * provided sub-directory using `self::usingSubDirectories()` is a match.
	 * Here, among other task, next scanning batch may be carried out using
	 * current path: `static::scan($this->currentItem()->getPathname())`.
	 */
	final protected function scanDirectory(): void {}

	/**
	 * Validates current item should be ignored by the scanner or not.
	 * By default, it validates item in the following order:
	 * - Ignores dot (parent directory link)
	 * - Allows file with `static::EXTENSION`.
	 * - Allows sub-directory registered with `self::usingSubDirectories()`.
	 */
	protected function currentItemIsIgnored(): bool {
		if ( ( $item = $this->currentItem() )->isDot() ) {
			return true;
		}

		if ( $this->isScannableFile() ) {
			return false;
		}

		if ( ! $item->isDir() ) {
			return true;
		}

		if ( ! $this->inCurrentDepth()->directoryExists() ) {
			return true;
		}

		$this->scanDirectory();

		return false;
	}

	/** Gets the filename without `static::EXTENSION`. */
	final protected function withoutExtension( string $name = null ): string {
		$suffix = '.' . static::EXTENSION;

		return $name ? substr( $name, 0, - strlen( $suffix ) ) : $this->currentItem()->getBasename( $suffix );
	}

	final protected function withValid( DirectoryIterator $item ): static {
		$this->scannedPaths[ $item->getPathname() ] = $this->withoutExtension( $item->getFilename() );

		return $this;
	}

	final protected function realDirectoryPath( string $path ): string {
		return realpath( $path ) ?: $this->throwInvalidDir( $path );
	}

	/**
	 * Scans files and directories inside the provided directory name.
	 * This may or may not be same value as `static::getRootPath()`
	 * based on whether directory is being recursively scanned.
	 */
	private function scan( string $directory ): static {
		$this->scannedDirectories[] = $directory;

		foreach ( new DirectoryIterator( $directory ) as $item ) {
			$this->currentlyScannedItem = $item;

			if ( ! $this->currentItemIsIgnored() ) {
				$this->withValid( $item )->execute();
			}
		}

		return $this;
	}

	private function inCurrentDepth(): self {
		if ( $this->subDirectories ) {
			$subPathParts       = $this->currentItemSubpath( parts: true );
			$this->currentDepth = array( count( $subPathParts ), $this->currentItem()->getFilename() );
		}

		return $this;
	}

	private function directoryExists(): bool {
		if ( ! $this->currentDepth ) {
			return false;
		}

		[$depth, $dirname]  = $this->currentDepth;
		$this->currentDepth = null;

		return in_array( $depth, (array) ( $this->subDirectories[ $dirname ] ?? array() ), strict: true );
	}

	/** @return ($parts is true ? list<string> : string) */
	private function currentItemSubpath( bool $parts = true ): string|array {
		if ( ! $this->currentItem()->valid() ) {
			return $parts ? array() : '';
		}

		$fullPath = $this->currentItem()->getPathname();
		$subpath  = trim( substr( $fullPath, strlen( $this->getRootPath() ) ), DIRECTORY_SEPARATOR );

		return $parts ? explode( separator: DIRECTORY_SEPARATOR, string: $subpath ) : $subpath;
	}

	private function throwInvalidDir( string $path ): never {
		throw new LogicException( sprintf( 'Impossible to scan in non-existing directory: "%s".', $path ) );
	}
}
