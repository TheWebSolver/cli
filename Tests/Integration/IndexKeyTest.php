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
		string $key,
		array $collection,
		array $disallowed,
		?string $thrown = null
	): void {

		if ( $thrown ) {
			$this->expectExceptionMessage( $thrown );
		}

		$indexKey = new IndexKey( $key, $collection, $disallowed );

		$this->assertSame( $key, $indexKey->validated()->value );
	}

	public static function provideCollectablesAndDisallowedKeys(): array {
		return [
			[ 'a', [ 'a', 'b', 'c' ], [] ],
			[ 'b', [ 'a', 'b', 'c' ], [ 'a' ] ],
			[
				'a',
				[ 'a' ],
				[ 'a' ],
				sprintf( IndexKey::CANNOT_USE, 'a' ) . ' ' . IndexKey::ONLY_DISALLOWED_OPTION,
			],
			[
				'b',
				[ 'a', 'b' ],
				[ 'b' ],
				sprintf( IndexKey::CANNOT_USE, 'b' ) . ' ' . sprintf( IndexKey::AVAILABLE_OPTION, '"a"' ),
			],
			[
				'b',
				[ 'a', 'b', 'c' ],
				[ 'b' ],
				sprintf( IndexKey::CANNOT_USE, 'b' ) . ' ' . sprintf( IndexKey::AVAILABLE_OPTION, '"a" | "c"' ),
			],
			[
				'b',
				[ 'c', 'd' ],
				[],
				sprintf( IndexKey::CANNOT_USE, 'b' ) . ' ' . sprintf( IndexKey::AVAILABLE_OPTION, '"c" | "d"' ),
			],
			[
				'b',
				[ 'c', 'd' ],
				[ 'e' ],
				sprintf( IndexKey::CANNOT_USE, 'b' ) . ' ' . sprintf( IndexKey::AVAILABLE_OPTION, '"c" | "d"' ),
			],
		];
	}
}
