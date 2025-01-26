<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use DirectoryIterator;

trait DirectoryScanner {
	public const EXTENSION = 'php';

	private DirectoryIterator $currentlyScannedItem;
	/** @var string[] */
	private array $scannedDirectories = array();
	/** @var array<string,int|int[]> */
	protected array $subDirectories = array();
	/** @var ?array{0:int,1:string} */
	private ?array $currentDepth = null;
	/** @var array<string,string> List of found filenames indexed by filePath. */
	protected array $scannedFiles;

	public function count(): int {
		return count( $this->scannedDirectories );
	}

	/** @return string[] */
	public function getScannedDirectories(): array {
		return $this->scannedDirectories;
	}

	/**
	 * Gets the root path of the sub-directory currently being scanned. If no sub-directories
	 * `static::usingDirectories()` are scanned, it must be same as directory being scanned.
	 */
	abstract protected function getRootPath(): string;

	/**
	 * Allows concrete to execute task for the non-ignored filenames inside scanned directory.
	 * Currently scanned item may be accessed within using `static::currentItem()` method.
	 */
	abstract protected function executeFor( string $filename, string $filepath ): void;

	/** @param array<string,int|int[]> $nameWithDepth */
	protected function usingDirectories( array $nameWithDepth ): static {
		$this->subDirectories = $nameWithDepth;

		return $this;
	}

	final protected function currentItem(): DirectoryIterator {
		return $this->currentlyScannedItem;
	}

	protected function isPHPFile(): bool {
		return $this->currentItem()->isFile() && $this->currentItem()->getExtension() === self::EXTENSION;
	}

	protected function isScannableDirectory(): bool {
		return $this->subDirectories && $this->currentItem()->isDir();
	}

	/**
	 * Exposes scannable directory which may be used to perform recursive scanning.
	 * This method is invoked inside `static::isIgnored()` by validating with the
	 * user provided sub-directories using `static::usingDirectories()` method.
	 * Inside this method, among others, may perform the next scanning batch
	 * using provided directory name: `static::scan($item->getPathname())`.
	 */
	protected function scannableDirectory(): void {}

	/**
	 * Validates current item should be ignored by the scanner or not.
	 * It doesn't ignore **PHP files** & **static::$subDirectories**.
	 */
	protected function isIgnored(): bool {
		if ( $this->isPHPFile() ) {
			return false;
		}

		if ( ! $this->isScannableDirectory() ) {
			return true;
		}

		if ( ! $this->inCurrentDepth()->subDirectoryExists() ) {
			return true;
		}

		$this->scannableDirectory();

		return false;
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

			if ( $item->isDot() ) {
				continue;
			}

			if ( $this->isIgnored() ) {
				continue;
			}

			$filename                        = $item->getBasename( '.' . self::EXTENSION );
			$pathname                        = $item->getPathname();
			$this->scannedFiles[ $pathname ] = $filename;

			$this->executeFor( $filename, $pathname );
		}

		return $this;
	}

	private function inCurrentDepth(): self {
		$pathname           = $this->currentItem()->getPathname();
		$subpath            = ltrim( substr( $pathname, strlen( $this->getRootPath() ) ), DIRECTORY_SEPARATOR );
		$this->currentDepth = array(
			count( explode( separator: DIRECTORY_SEPARATOR, string: $subpath ) ),
			$this->currentItem()->getFilename(),
		);

		return $this;
	}

	private function subDirectoryExists(): bool {
		if ( ! $this->currentDepth ) {
			return false;
		}

		[$depth, $name]     = $this->currentDepth;
		$this->currentDepth = null;

		return in_array( $depth, (array) ( $this->subDirectories[ $name ] ?? array() ), strict: true );
	}
}
