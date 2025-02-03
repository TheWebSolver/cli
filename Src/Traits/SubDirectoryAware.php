<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use DirectoryIterator;

trait SubDirectoryAware {
	/** @var array<string,int|int[]> */
	private array $subDirectories = array();

	// phpcs:ignore PHPCompatibility.Classes.ForbiddenAbstractPrivateMethods.Found -- in trait OK.
	abstract private function scan( string $directory ): static;
	abstract protected function currentItem(): DirectoryIterator;

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
}
