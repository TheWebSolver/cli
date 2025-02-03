<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use LogicException;
use DirectoryIterator;

trait DirectoryScanner {
	private DirectoryIterator $currentScannedItem;
	/** @var string[] */
	private array $scannedDirectories = array();
	/** @var array{0:int,1:string} */
	private array $currentDepth;
	/** @var array<string,string> */
	private array $scannedPaths;

	final public function count(): int {
		return count( $this->scannedDirectories );
	}

	/** @return string[] List of scanned directory/sub-directory real paths. */
	final public function getScannedDirectories(): array {
		return $this->scannedDirectories;
	}

	/** @return array<string,string> List of found file/directory name indexed by its realpath. */
	final public function getScannedItems(): array {
		return $this->scannedPaths;
	}

	/** Gets root directory path of the sub-directory currently being scanned. */
	abstract protected function getRootPath(): string;

	/**
	 * Allows the implementing class to perform task for the current file.
	 * Here, `$this->currentItem()->isFile()` will always return `true`.
	 */
	abstract protected function forCurrentFile(): void;

	protected function getRootBasename(): string {
		$root = rtrim( $this->getRootPath(), DIRECTORY_SEPARATOR );

		return ltrim( substr( $root, strrpos( $root, DIRECTORY_SEPARATOR, -1 ) ?: 0 ), DIRECTORY_SEPARATOR );
	}

	/**
	 * Gets file extensions that the scanner is allowed to scan.
	 * By default, it'll only scan files with `.php` extension.
	 *
	 * @return string[]
	 */
	protected function getAllowedExtensions(): array {
		return array( 'php' );
	}

	final protected function currentItem(): DirectoryIterator {
		return $this->currentScannedItem;
	}

	/** Returns extension (without (.) dot) if given filename extension is allowed, else null. */
	protected function extensionOf( string $filename ): ?string {
		$nameParts = explode( separator: '.', string: $filename );

		return in_array( $ext = end( $nameParts ), $this->getAllowedExtensions(), strict: true ) ? $ext : null;
	}

	/**
	 * Acts as a safeguard as to whether current item should be considered as a scanned item or not.
	 * By default, this will:
	 *  - return true only when `$this->currentItem()->isFile()` and has one of the allowed extensions
	 *  - invoke`$this->forCurrentSubDirectory()` if exhibiting class uses `SubDirectoryAware` trait.
	 */
	protected function shouldRegisterCurrentItem(): bool {
		if ( $this->currentItem()->isDot() ) {
			return false;
		}

		if ( $this->currentItemIsFileWithAllowedExtension() ) {
			return true;
		}

		if ( ! $this->isSubDirectoryAwareAndCurrentItemIsDir() ) {
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

	/** @throws LogicException When given `$path` is not a real directory path. */
	final protected function realDirectoryPath( string $path ): string {
		return realpath( $path ) ?: $this->throwInvalidDir( $path );
	}

	final protected function exhibitUsesTrait( string $name ): bool {
		return in_array( $name, class_uses( $this, autoload: false ), strict: true );
	}

	/**
	 * Scans files and directories inside the provided directory name.
	 * This may or may not be same value as `$this->getRootPath()`
	 * based on whether directory is being recursively scanned.
	 */
	private function scan( string $directory = null ): static {
		$directory                  = $this->realDirectoryPath( $directory ?? $this->getRootPath() );
		$scanner                    = new DirectoryIterator( $directory );
		$this->scannedDirectories[] = $directory;

		$this->realDirectoryPath( $this->getRootPath() ) === $directory
			&& $this->exhibitUsesTrait( ScannedItemAware::class )
			&& $this->maybeRegisterCurrentDepth( count: 0, item: $scanner );

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

	protected function currentItemIsFileWithAllowedExtension(): bool {
		$item    = $this->currentItem();
		$isValid = $item->isFile() && in_array( $item->getExtension(), $this->getAllowedExtensions(), strict: true );

		if ( $isValid ) {
			( $count = count( $subPathParts = $this->currentItemSubpath( parts: true ) ?? array() ) )
				&& $this->maybeRegisterCurrentDepth( $count, parts: $subPathParts );
		}

		return $isValid;
	}

	protected function isSubDirectoryAwareAndCurrentItemIsDir(): bool {
		return $this->currentItem()->isDir() && $this->exhibitUsesTrait( SubDirectoryAware::class );
	}

	private function inCurrentDepth(): self {
		if ( $this->subDirectories ) {
			$subPathParts       = $this->currentItemSubpath( parts: true ) ?? array();
			$this->currentDepth = array( $count = count( $subPathParts ), $this->currentItem()->getBasename() );

			$count && $this->maybeRegisterCurrentDepth( $count, parts: $subPathParts );
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
		$fullPath = $this->currentItem()->getRealPath();
		$subpath  = trim(
			string: substr( $fullPath, strlen( $this->realDirectoryPath( $this->getRootPath() ) ) ),
			characters: DIRECTORY_SEPARATOR
		);

		return $parts ? explode( separator: DIRECTORY_SEPARATOR, string: $subpath ) : $subpath;
	}

	/** @param string[] $parts */
	private function maybeRegisterCurrentDepth(
		int $count,
		DirectoryIterator $item = null,
		array $parts = array()
	): void {
		$this->exhibitUsesTrait( ScannedItemAware::class )
		// @phpstan-ignore-next-line -- Defined method of "ScannedItemAware" trait.
			&& $this->registerCurrentItemDepth( $parts, $count + 1, clone ( $item ?? $this->currentItem() ) );
	}

	private function throwInvalidDir( string $path ): never {
		throw new LogicException( sprintf( 'Impossible to scan in non-existing directory: "%s".', $path ) );
	}
}
