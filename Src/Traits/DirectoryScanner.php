<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use SplFileInfo;
use LogicException;
use DirectoryIterator;

trait DirectoryScanner {
	final public const ROOT_LEVEL = 1;

	private DirectoryIterator $currentScannedItem;
	/** @var string[] */
	private array $scannedDirectories = array();
	/** @var array<string,string> */
	private array $scannedPaths;

	final public function count(): int {
		return count( $this->scannedDirectories );
	}

	/** @return string[] List of scanned directory (and sub-directory, if aware) real paths. */
	final public function getScannedDirectories(): array {
		return $this->scannedDirectories;
	}

	/** @return array<string,string> List of found file (and sub-directory, if aware) name indexed by its realpath. */
	final public function getScannedItems(): array {
		return $this->scannedPaths;
	}

	/** Gets raw or real root directory path currently being scanned. */
	abstract protected function getRootPath(): string;

	/**
	 * Allows the implementing class to perform task for the current file.
	 * Here, `$this->currentItem()->isFile()` will always return `true`.
	 */
	abstract protected function forCurrentFile(): void;

	final protected function currentItem(): DirectoryIterator {
		return $this->currentScannedItem;
	}

	/** Returns extension (without (.) dot) if given filename extension is allowed, else null. */
	final protected function extensionOf( string $filename ): ?string {
		$nameParts = explode( separator: '.', string: $filename );

		return in_array( $ext = end( $nameParts ), $this->getAllowedExtensions(), strict: true ) ? $ext : null;
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

	final protected function getRootBasename(): string {
		$root = rtrim( $this->getRootPath(), DIRECTORY_SEPARATOR );

		return ltrim( substr( $root, strrpos( $root, DIRECTORY_SEPARATOR, -1 ) ?: 0 ), DIRECTORY_SEPARATOR );
	}

	/** @return ($parts is true ? ?list<string> : ?string) */
	final protected function currentItemSubpath( bool $parts = true ): string|array|null {
		$fullPath = $this->currentItem()->getRealPath();
		$subpath  = trim(
			string: substr( $fullPath, strlen( $this->realDirectoryPath( $this->getRootPath() ) ) ),
			characters: DIRECTORY_SEPARATOR
		);

		return $parts ? explode( separator: DIRECTORY_SEPARATOR, string: $subpath ) : $subpath;
	}

	/**
	 * Scans files and directories inside the provided directory name.
	 * `$directory` may or may not be same as `$this->getRootPath()`
	 * based on whether exhibit is using `SubDirectoryAware`.
	 */
	final protected function scan( string $directory = null ): static {
		$directory                  = $this->realDirectoryPath( $directory ?? $this->getRootPath() );
		$scanner                    = new DirectoryIterator( $directory );
		$this->scannedDirectories[] = $directory;

		$this->realDirectoryPath( $this->getRootPath() ) === $directory
			&& $this->maybeRegisterCurrentDepth( count: 0, parts: array(), item: $scanner );

		while ( $scanner->valid() ) {
			$this->currentScannedItem = $scanner->current();

			$this->shouldRegisterCurrentItem() && $this->registerScannedPath()->forCurrentFile();

			$scanner->next();
		}

		return $this;
	}

	/**
	 * Gets file extensions that the scanner is allowed to scan ( defaults to `php` extension).
	 *
	 * @return string[]
	 */
	protected function getAllowedExtensions(): array {
		return array( 'php' );
	}

	/** Determines whether the found file item should be scanned or not. */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Intended to be used by exhibiting class.
	protected function shouldRegisterCurrentFile( SplFileInfo $item ): bool {
		return true;
	}

	/** @param class-string $traitName */
	private function exhibitIsUsing( string $traitName ): bool {
		return in_array( $traitName, haystack: class_uses( $this, autoload: false ), strict: true );
	}

	/** @param string[] $parts */
	private function maybeRegisterCurrentDepth( int $count, array $parts, DirectoryIterator $item = null ): void {
		$depth = self::ROOT_LEVEL + $count;

		$this->exhibitIsUsing( ScannedItemAware::class )
			&& $this->registerCurrentItemDepth( $parts, $depth, clone ( $item ?? $this->currentItem() ) );
	}

	private function currentItemIsFileWithAllowedExtension(): bool {
		$item            = $this->currentItem();
		$isScannableFile = $item->isFile()
			&& in_array( $item->getExtension(), $this->getAllowedExtensions(), strict: true )
			&& $this->shouldRegisterCurrentFile( $item );

		$isScannableFile
			&& ( $count = count( $subPathParts = $this->currentItemSubpath( parts: true ) ?? array() ) )
			&& $this->maybeRegisterCurrentDepth( $count, parts: $subPathParts );

		return $isScannableFile;
	}

	private function exhibitIsSubDirectoryAware(): bool {
		if ( ! $this->currentItem()->isDir() || ! $this->exhibitIsUsing( SubDirectoryAware::class ) ) {
			return false;
		}

		$tree = $this->currentSubDirectoryTree();
		( $count = count( $tree ) ) && $this->maybeRegisterCurrentDepth( $count, parts: $tree );

		return ! ! $count;
	}

	/**
	 * Acts as a safeguard as to whether current item should be considered as a scanned item or not.
	 * By default, this will:
	 *  - return true only when `$this->currentItem()->isFile()` and has one of the allowed extensions
	 *  - invoke`$this->forCurrentSubDirectory()` if exhibiting class is using `SubDirectoryAware`.
	 */
	private function shouldRegisterCurrentItem(): bool {
		if ( $this->currentItem()->isDot() ) {
			return false;
		}

		if ( $this->currentItemIsFileWithAllowedExtension() ) {
			return true;
		}

		$this->exhibitIsSubDirectoryAware() && $this->registerScannedPath()->forCurrentSubDirectory();

		return false;
	}

	private function registerScannedPath(): static {
		$this->scannedPaths[ $this->currentItem()->getRealPath() ] = $this->withoutExtension();

		return $this;
	}

	private function throwInvalidDir( string $path ): never {
		throw new LogicException( sprintf( 'Impossible to scan in non-existing directory: "%s".', $path ) );
	}
}
