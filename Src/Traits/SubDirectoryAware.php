<?php // phpcs:disable PHPCompatibility.Classes.ForbiddenAbstractPrivateMethods.Found -- in trait OK.
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use DirectoryIterator;

trait SubDirectoryAware {
	/** @var array<string,int[]> */
	private array $subDirectories = array();

	/**
	 * Registers sub-directory name to be scanned.
	 *
	 * @param int $depth     The depth in which sub-directory name to be discovered.
	 * @param int ...$depths Additional depths for same sub-directory name in any other sub-directories
	 *                       that are registered to be discovered.
	 */
	final public function usingSubDirectory( string $name, int $depth = 2, int ...$depths ): static {
		$registered                    = (array) ( $this->subDirectories[ $name ] ?? array() );
		$this->subDirectories[ $name ] = array_unique( array( $depth, ...$depths, ...$registered ) );

		return $this;
	}

	/**
	 * @param array<string,int|int[]> $nameWithDepth Sub-directory name and its depth (depths if same
	 *                                               name exists in nested directory) to be scanned.
	 */
	final public function usingSubDirectories( array $nameWithDepth ): static {
		foreach ( $nameWithDepth as $name => $depth ) {
			$this->usingSubDirectory( $name, ...( (array) $depth ) );
		}

		return $this;
	}

	abstract protected function currentItemSubpath( bool $parts = true ): string|array|null;
	abstract protected function currentItem(): DirectoryIterator;
	abstract protected function scan( string $directory ): static;

	/** @return string[] */
	final protected function currentSubDirectoryTree(): array {
		return $this->subDirectoryExists( $tree = ( $this->ofCurrentDepth() ?? array() ) ) ? $tree : array();
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

	/** @return ?string[] */
	private function ofCurrentDepth(): ?array {
		return $this->subDirectories ? $this->currentItemSubpath( parts: true ) : null;
	}

	/** @param string[] $tree */
	private function subDirectoryExists( array $tree ): bool {
		$dirname = $this->currentItem()->getBasename();

		return array_key_exists( $dirname, $this->subDirectories )
			&& in_array( count( $tree ), $this->subDirectories[ $dirname ], strict: true );
	}
}
