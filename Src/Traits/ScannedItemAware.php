<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use DirectoryIterator;

trait ScannedItemAware {
	/** @var array<string,array<int,array{depth:int,base:string,type:string,tree:string[],item:DirectoryIterator}>> */
	private array $scannedItemsDepth;

	abstract protected function currentItem(): DirectoryIterator;
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

	/** @param string[] $parts */
	private function registerCurrentItemDepth( array $parts, int $depth ): void {
		array_pop( $parts );

		$item = clone $this->currentItem(); // Catch current item.
		$tree = array( $rootBasename = $this->getRootBasename(), ...( $parts ?: array() ) );
		$type = $item->isDir() ? 'directory' : 'file';
		$base = $item->getBasename();

		$this->scannedItemsDepth[ $rootBasename ][] = compact( 'depth', 'base', 'type', 'tree', 'item' );
	}
}
