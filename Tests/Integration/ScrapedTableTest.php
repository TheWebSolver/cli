<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\ScrapedTable;

class ScrapedTableTest extends TestCase {
	#[Test]
	public function itEnsuresGetterWorksWithoutAnyBuild(): void {
		$table = new ScrapedTable( $this->createStub( OutputInterface::class ) );

		$this->assertSame( Console::SUCCESS, $table->getStatusCode() );
		$this->assertSame( [ 'green', 'black' ], $table->getConsoleColors() );
		$this->assertSame( [ 'Skipped parsing and caching to a file', Symbol::Tick ], $table->getFooter() );
	}

	#[Test]
	public function itEnsuresBuilderIsBuiltWithProvidedArguments(): void {
		$table = new ScrapedTable( $this->createStub( OutputInterface::class ) );

		$table->fetchedItemsCount( 5 );

		$built = $table->builder->build( $table, 'test' );

		$this->assertCount( 1, $built );
		$this->assertSame( Symbol::Green->value, (string) $built['fetch']['Status'] );
		$this->assertSame( 'No. of test Fetched', $built['fetch']['Action'] );
		$this->assertSame( 5, $built['fetch']['Details'] );

		$built = $table->accentedCharacters( 'escaped' )->builder->build( $table, 'test' );

		$this->assertCount( 2, $built );

		$this->assertSame( Symbol::Green->value, (string) $built['accents']['Status'] );
		$this->assertSame( 'Accented Characters', $built['accents']['Action'] );
		$this->assertSame( 'escaped', $built['accents']['Details'] );

		$built = $table->accentedCharacters( null )->builder->build( $table, 'test' );

		$this->assertCount( 2, $built );

		$this->assertSame( Symbol::Yellow->value, (string) $built['accents']['Status'] );
		$this->assertSame( 'Accented Characters', $built['accents']['Action'] );
		$this->assertSame( 'N/A', $built['accents']['Details'] );
	}

	#[Test]
	#[DataProvider( 'provideCollectionDetails' )]
	public function itBuildsCollectionDetails( array $args, int $count, array $KeysInfo, ?array $indexInfo = null ): void {
		$table = new ScrapedTable( $this->createStub( OutputInterface::class ) );
		$built = $table->collectedUsing( ...$args )->builder->build( $table, 'test' );

		$this->assertCount( $count, $built );

		[$status, $action, $details] = $KeysInfo;

		$this->assertSame( $status, (string) $built['keys']['Status'] );
		$this->assertSame( "Collection Key{$action}", $built['keys']['Action'] );
		$this->assertSame( $details, $built['keys']['Details'] );

		if ( ! $indexInfo ) {
			return;
		}

		[$status, $details] = $indexInfo;

		$this->assertSame( $status, (string) $built['index']['Status'] );
		$this->assertSame( 'Indexed by Value of', $built['index']['Action'] );
		$this->assertSame( $details, $built['index']['Details'] );
	}

	public static function provideCollectionDetails(): array {
		return [
			[ [ [ 'a' ], null ], 1, [ Symbol::Green->value, '', '"a"' ] ],
			[
				[ [ 'a' ], 'b' ],
				2,
				[ Symbol::Green->value, '', '"a"' ],
				[ Symbol::NotAllowed->value, 'N/A (Possible option is: "a")' ],
			],
			[
				[ [ 'a', 'c' ], 'b' ],
				2,
				[ Symbol::Green->value, 's', '"a" | "c"' ],
				[ Symbol::NotAllowed->value, 'N/A (Possible option is one of: "a" | "c")' ],
			],
			[
				[ [ 'a', 'c' ], 'b', 'a' ],
				2,
				[ Symbol::Green->value, 's', '"a" | "c"' ],
				[ Symbol::NotAllowed->value, 'N/A (Possible option is: "c")' ],
			],
			[
				[ [ 'a', 'b', 'c' ], 'b' ],
				2,
				[ Symbol::Green->value, 's', '"a" | "b" | "c"' ],
				[ Symbol::Green->value, 'b' ],
			],
		];
	}

	#[Test]
	#[DataProvider( 'provideCacheDetails' )]
	public function itBuildsCacheDetails(
		array $args,
		array $pathInfo,
		array $byteInfo,
		array $footerDetails,
		?array $consoleColors = null
	): void {
		$table           = new ScrapedTable( $this->createStub( OutputInterface::class ) );
		$built           = $table->withCacheDetails( $args )->builder->build( $table, 'test' );
		$success         = null !== $consoleColors;
		$consoleColors ??= [ 'red', '#eee' ];

		$this->assertCount( 2, $built );
		$this->assertSame( $consoleColors ?? [ 'green', 'black' ], $table->getConsoleColors() );
		$this->assertSame( $footerDetails, $table->getFooter() );
		$this->assertSame( $success, $table->isSuccess() );

		$this->assertSame( $pathInfo[0], (string) $built['path']['Status'] );
		$this->assertSame( 'Cache Filepath', $built['path']['Action'] );
		$this->assertSame( $pathInfo[1] ?? $args[0], $built['path']['Details'] );

		$this->assertSame( $byteInfo[0], (string) $built['byte']['Status'] );
		$this->assertSame( 'Total Bytes Written', $built['byte']['Action'] );
		$this->assertSame( $byteInfo[1] ?? $args[2], $built['byte']['Details'] );
	}

	public static function provideCacheDetails(): array {
		return [
			[
				[ 'both.pass', 'content', 7 ],
				[ Symbol::Green->value ],
				[ Symbol::Green->value ],
				[ 'Extracted and cached to a file: both.pass', Symbol::Tick ],
				[ 'green', 'black' ],
			],
			[
				[ 'content.fail', false, 7 ],
				[ Symbol::Red->value ],
				[ Symbol::Green->value ],
				[ 'Could not extract but cached to a file: content.fail', Symbol::Cross ],
			],
			[
				[ 'byte.fail', 'content', false ],
				[ Symbol::Green->value ],
				[ Symbol::Red->value, 0 ],
				[ 'Extracted but could not cache to a file: byte.fail', Symbol::Cross ],
			],
			[
				[ 'both.fail', false, false ],
				[ Symbol::Red->value ],
				[ Symbol::Red->value, 0 ],
				[ 'Could not extract and could not cache to a file: both.fail', Symbol::Cross ],
			],
			[
				[ 'byte.technically.pass', false, 0 ],
				[ Symbol::Red->value ],
				[ Symbol::Green->value ],
				[ 'Could not extract but cached to a file: byte.technically.pass', Symbol::Cross ],
			],
		];
	}
}
