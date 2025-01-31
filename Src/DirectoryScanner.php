<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use LogicException;
use DirectoryIterator;

trait DirectoryScanner {
	/** @var string[] */
	public const ALLOWED_EXTENSIONS = array( 'php' );

	private DirectoryIterator $currentScannedItem;
	/** @var string[] */
	private array $scannedDirectories = array();
	/** @var array<string,int|int[]> */
	private array $subDirectories = array();
	/** @var array{0:int,1:string} */
	private array $currentDepth;
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
	 * If no sub-directory is provided using `$this->usingSubDirectories()`,
	 * it must return same value as the current directory being scanned.
	 */
	abstract protected function getRootPath(): string;

	/**
	 * Allows the implementing class to perform task for the current file.
	 * Here, `$this->currentItem()->isFile()` will always return `true`.
	 */
	abstract protected function forCurrentFile(): void;

	/**
	 * Allows the implementing class to perform task for the current sub-directory.
	 * This is invoked inside `$this->shouldRegisterCurrentItem()` method when a
	 * user provided sub-directory name (using `$this->usingSubDirectories()`)
	 * matches. Then, among other tasks, user may perform next scan using
	 * current item: `$this->scan($this->currentItem()->getPathname())`.
	 * Here, `$this->currentItem()->isDir()` will always return `true`.
	 *
	 * @throws LogicException When sub-directory names are provided with `$this->usingSubDirectories()`
	 *                        method but the class using this scanner does not implement this method.
	 */
	protected function forCurrentSubDirectory(): void {
		throw new LogicException(
			sprintf(
				'Class "%1$s" must implement "%2$s" method to scan "%3$s" sub-directory.',
				static::class,
				DirectoryScanner::class . '::' . __FUNCTION__,
				$this->currentItem()->getFilename()
			)
		);
	}

	/**
	 * @param array<string,int|int[]> $nameWithDepth Sub-directory name and its depth (depths if same
	 *                                               name exists in nested directory) to be scanned.
	 */
	final protected function usingSubDirectories( array $nameWithDepth ): static {
		$this->subDirectories = $nameWithDepth;

		return $this;
	}

	final protected function currentItem(): DirectoryIterator {
		return $this->currentScannedItem;
	}

	/** Returns extension (without (.) dot) if given filename extension is allowed, else null. */
	protected function extensionOf( string $filename ): ?string {
		$nameParts = explode( separator: '.', string: $filename );

		return in_array( $ext = end( $nameParts ), static::ALLOWED_EXTENSIONS, strict: true ) ? $ext : null;
	}

	/**
	 * Acts as a safeguard as to whether current item should be considered as a scanned item or not.
	 * By default, this will:
	 *  - return true only when `$this->currentItem()->isFile()` and has one of the allowed extensions
	 *  - invoke the template method `$this->forCurrentSubDirectory()` if sub-directory name matches
	 */
	protected function shouldRegisterCurrentItem(): bool {
		if ( ( $item = $this->currentItem() )->isDot() ) {
			return false;
		}

		if ( $this->currentItemIsFileWithAllowedExtension() ) {
			return true;
		}

		if ( ! $item->isDir() ) {
			return false;
		}

		if ( $this->inCurrentDepth()->subDirectoryExists() ) {
			$this->registerScannedPath()->forCurrentSubDirectory();
		}

		return false;
	}

	final protected function withoutExtension( string $filename = null ): string {
		$ext = $this->extensionOf( $filename ?? $this->currentItem()->getFilename() ) ?? '';

		return ! $filename
			? $this->currentItem()->getBasename( ".{$ext}" )
			: ( $ext ? substr( $filename, 0, - strlen( ".{$ext}" ) ) : $filename );
	}

	final protected function realDirectoryPath( string $path ): string {
		return realpath( $path ) ?: $this->throwInvalidDir( $path );
	}

	/**
	 * Scans files and directories inside the provided directory name.
	 * This may or may not be same value as `$this->getRootPath()`
	 * based on whether directory is being recursively scanned.
	 */
	private function scan( string $directory ): static {
		$this->scannedDirectories[] = $directory;
		$scanner                    = new DirectoryIterator( $directory );

		while ( $scanner->valid() ) {
			$this->currentScannedItem = $scanner->current();

			$this->shouldRegisterCurrentItem() && $this->registerScannedPath()->forCurrentFile();

			$scanner->next();
		}

		return $this;
	}

	private function registerScannedPath(): static {
		$this->scannedPaths[ $this->currentItem()->getPathname() ] = $this->withoutExtension();

		return $this;
	}

	private function currentItemIsFileWithAllowedExtension(): bool {
		$item = $this->currentItem();

		return $item->isFile() && in_array( $item->getExtension(), static::ALLOWED_EXTENSIONS, strict: true );
	}

	private function inCurrentDepth(): self {
		if ( $this->subDirectories ) {
			$subPathParts       = $this->currentItemSubpath( parts: true ) ?? array();
			$this->currentDepth = array( count( $subPathParts ), $this->currentItem()->getFilename() );
		}

		return $this;
	}

	private function subDirectoryExists(): bool {
		if ( ! ( $this->currentDepth ?? false ) ) {
			return false;
		}

		[$depth, $dirname] = $this->currentDepth;

		unset( $this->currentDepth );

		return array_key_exists( $dirname, $this->subDirectories )
			&& in_array( $depth, (array) ( $this->subDirectories[ $dirname ] ), strict: true );
	}

	/** @return ($parts is true ? ?list<string> : ?string) */
	private function currentItemSubpath( bool $parts = true ): string|array|null {
		$fullPath = $this->currentItem()->getPathname();
		$subpath  = trim( substr( $fullPath, strlen( $this->getRootPath() ) ), DIRECTORY_SEPARATOR );

		return $parts ? explode( separator: DIRECTORY_SEPARATOR, string: $subpath ) : $subpath;
	}

	private function throwInvalidDir( string $path ): never {
		throw new LogicException( sprintf( 'Impossible to scan in non-existing directory: "%s".', $path ) );
	}
}
