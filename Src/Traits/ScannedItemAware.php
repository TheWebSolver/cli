<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use DirectoryIterator;

trait ScannedItemAware {
	/** @var array<string,array<int,array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator}>> */
	private array $scannedItemsDepth;

	abstract protected function getRootBasename(): string;

	/** @return array<string,array<int,array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator}>> */
	public function getScannedItemsDepth( bool $sortByDepth = false ): array {
		$depths = $this->scannedItemsDepth;

		if ( ! $sortByDepth ) {
			return $depths;
		}

		$sortByDepthKey = static fn( array $a, array $b ) => $a['depth'] <=> $b['depth'];

		array_walk( $depths, static fn( array &$items ) => uasort( $items, $sortByDepthKey( ... ) ) );

		return $depths;
	}

	public function getMaxDepth(): int {
		return max( array_reduce( $this->getScannedItemsDepth(), $this->toOnlyDepth( ... ), initial: array() ) );
	}

	/** @param string[] $parts */
	private function registerCurrentItemDepth( array $parts, int $depth, DirectoryIterator $item ): void {
		$rootBasename = $this->getRootBasename();
		$isNotRoot    = ! ! array_pop( $parts );
		$tree         = $isNotRoot ? array( $rootBasename, ...( $parts ?: array() ) ) : array();
		$type         = $item->isDir() ? 'directory' : 'file';
		$base         = $item->getBasename();

		$this->scannedItemsDepth[ $rootBasename ][] = compact( 'depth', 'base', 'type', 'tree', 'item' );
	}

	/**
	 * @param int[]                                                                                    $depths
	 * @param array<int,array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator}> $items
	 * @return int[]
	 */
	private function toOnlyDepth( array $depths, array $items ): array {
		return array( ...$depths, ...array_column( $items, column_key: 'depth' ) );
	}
}
