<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\IndexKey;

class IndexKeyTest extends TestCase {
	#[Test]
	#[DataProvider( 'provideCollectablesAndDisallowedKeys' )]
	public function itValidatesIndexKeyWithCollectableAndDisallowedKeys(
		?string $key,
		array $collection,
		array $disallowed,
		?string $thrown = null
	): void {
		if ( $thrown ) {
			$this->expectExceptionMessage( sprintf( IndexKey::INVALID, $key ) . ' ' . $thrown );
		}

		$this->assertSame( $key, ( new IndexKey( $key, $collection, $disallowed ) )->validated()->value );
	}

	public static function provideCollectablesAndDisallowedKeys(): array {
		return [
			[ '', [ 'a', 'b', 'c' ], [] ],
			[ null, [ 'a', 'b', 'c' ], [] ],
			[ 'a', [ 'a', 'b', 'c' ], [] ],
			[ 'b', [ 'a', 'b', 'c' ], [ 'a' ] ],
			[ 'b', [ 'a', 'b', 'c' ], [ 'd', 'e', 'f' ] ],
			[ 'a', [ 'a' ], [ 'a' ], sprintf( IndexKey::NON_COLLECTABLE, '"a"' ) ],
			[ 'a', [ 'a', 'b' ], [ 'a', 'b' ], sprintf( IndexKey::NON_COLLECTABLE, '"a" | "b"' ) ],
			[ 'b', [ 'a', 'b' ], [ 'b' ], sprintf( IndexKey::ALLOWED_COLLECTABLE, '"a"' ) ],
			[ 'b', [ 'a', 'b', 'c' ], [ 'b' ], sprintf( IndexKey::ALLOWED_COLLECTABLE, '"a" | "c"' ) ],
			[ 'b', [ 'c', 'd' ], [], sprintf( IndexKey::ALLOWED_COLLECTABLE, '"c" | "d"' ) ],
			[ 'b', [ 'c', 'd' ], [ 'e' ], sprintf( IndexKey::ALLOWED_COLLECTABLE, '"c" | "d"' ) ],
			[ 'a', [], [], IndexKey::EMPTY_COLLECTABLE ],
		];
	}
}
