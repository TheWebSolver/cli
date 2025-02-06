<?php // phpcs:disable PHPCompatibility.Classes.ForbiddenAbstractPrivateMethods.Found -- in trait OK.
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use DirectoryIterator;

trait SubDirectoryAware {
	final public const ROOT_LEVEL = 1;

	/** @var array<string,int[]> */
	private array $subDirectories = array();


	/**
	 * Registers given sub-directory name for scanning when it is found in given depth(s).
	 *
	 * @param int $depth     The depth in which the sub-directory name to be discovered.
	 *                       Provide depth by counting root at the first depth (**1**).
	 * @param int ...$depths Additional depths with same sub-directory name in other
	 *                       sub-directories that're also registered for discovery.
	 */
	final public function usingSubDirectory( string $name, int $depth = 2, int ...$depths ): static {
		$registered                    = $this->subDirectories[ $name ] ?? array();
		$this->subDirectories[ $name ] = array_unique( array( $depth, ...$depths, ...$registered ) );

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
	 * When sub-directory names (`$this->usingSubDirectory()`) are provided and
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

		return ( $depths = ( $this->subDirectories[ $dirname ] ?? array() ) )
			&& in_array( self::ROOT_LEVEL + count( $tree ), $depths, strict: true );
	}
}
