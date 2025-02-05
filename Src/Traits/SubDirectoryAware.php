<?php // phpcs:disable PHPCompatibility.Classes.ForbiddenAbstractPrivateMethods.Found -- in trait OK.
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use DirectoryIterator;

trait SubDirectoryAware {
	/** @var array<string,int|int[]> */
	private array $subDirectories = array();

	abstract protected function currentItem(): DirectoryIterator;
	abstract private function scan( string $directory ): static;
	abstract private function currentItemSubpath( bool $parts = true ): string|array|null;

	/**
	 * @param array<string,int|int[]> $nameWithDepth Sub-directory name and its depth (depths if same
	 *                                               name exists in nested directory) to be scanned.
	 */
	final protected function usingSubDirectories( array $nameWithDepth ): static {
		$this->subDirectories = $nameWithDepth;

		return $this;
	}

	/**
	 * Allows the exhibiting class to perform task for the current sub-directory.
	 * When sub-directory names (`$this->usingSubDirectories()`) are provided &
	 * current item is a match, it handles the next scan batch automatically
	 * using its path: `$this->scan($this->currentItem()->getRealPath())`.
	 * Here, `$this->currentItem()->isDir()` will always return `true`.
	 * This behavior can be overridden by the exhibiting class to do
	 * additional task by using the current directory item info.
	 */
	protected function forCurrentSubDirectory(): void {
		$this->scan( $this->currentItem()->getRealPath() );
	}

	/** @return string[] */
	private function currentSubDirectoryTree(): array {
		return $this->subDirectoryExists( $tree = ( $this->ofCurrentDepth() ?? array() ) ) ? $tree : array();
	}

	/** @return ?string[] */
	private function ofCurrentDepth(): ?array {
		return $this->subDirectories ? $this->currentItemSubpath( parts: true ) : null;
	}

	/** @param string[] $tree */
	private function subDirectoryExists( array $tree ): bool {
		$dirname = $this->currentItem()->getBasename();

		return array_key_exists( $dirname, $this->subDirectories )
			&& in_array( count( $tree ), (array) ( $this->subDirectories[ $dirname ] ), strict: true );
	}
}
