<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use DirectoryIterator;

trait ScannedItemAware {
	/** @var array<string,array<int,array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator}>> */
	private array $scannedItemsDepth;
	private int $treeMaxDepth;

	abstract protected function getRootBasename(): string;

	/** @return array<string,array<int,array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator}>> */
	final public function getScannedItemsDepth( bool $sortByDepth = false ): array {
		( $depths = $this->scannedItemsDepth ) &&
			$sortByDepth && array_walk( $depths, self::inAscendingOrder( ... ) );

		return $depths;
	}

	final public function getMaxDepth(): int {
		return $this->treeMaxDepth
			??= ( $depths = array_reduce( $this->getScannedItemsDepth(), self::toOnlyDepths( ... ), initial: array() ) )
				? max( $depths )
				: 0;
	}

	/** @param string[] $parts */
	final protected function registerCurrentItemDepth( array $parts, int $depth, DirectoryIterator $item ): void {
		$rootBasename = $this->getRootBasename(); // Store items indexed by root dir.
		$isNotRoot    = ! ! array_pop( $parts );  // Omit tree structure for root dir.
		$tree         = $isNotRoot ? array( $rootBasename, ...( $parts ?: array() ) ) : array();
		$type         = $item->isDir() ? 'directory' : 'file';
		$base         = $this->inferIfScannedIsRoot( $item );

		// Clear previously cached max depth calculation, if any.
		unset( $this->treeMaxDepth );

		$this->scannedItemsDepth[ $rootBasename ][] = compact( 'depth', 'base', 'type', 'tree', 'item' );
	}

	/** @param array<int,array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator}> $items */
	private static function inAscendingOrder( array &$items ): void {
		uasort( $items, self::sortInAscendingOrder( ... ) );
	}

	/**
	 * @param array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator} $a
	 * @param array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator} $b
	 */
	private static function sortInAscendingOrder( array $a, array $b ): int {
		return $a['depth'] <=> $b['depth'];
	}

	/**
	 * @param int[]                                                                                    $depths
	 * @param array<int,array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator}> $items
	 * @return int[]
	 */
	private static function toOnlyDepths( array $depths, array $items ): array {
		return array( ...$depths, ...array_column( $items, column_key: 'depth' ) );
	}

	private function inferIfScannedIsRoot( DirectoryIterator $item ): string {
		return ( '..' !== ( $base = $item->getBasename() ) ) ? $base : $this->getRootBasename();
	}
}
