<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\IndexKey;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\ScrapedTable;

class ScrapedTableTest extends TestCase {
	private ScrapedTable $table;

	protected function setUp(): void {
		$this->table = new ScrapedTable( $this->createStub( OutputInterface::class ) );
	}

	protected function tearDown(): void {
		unset( $this->table );
	}

	#[Test]
	public function itEnsuresGetterWorksWithoutAnyBuild(): void {
		$this->assertSame( 0, $this->table->getStatusCode() );
		$this->assertSame( [ 'green', 'black' ], $this->table->getConsoleColors() );
		$this->assertSame( [ 'Skipped parsing and caching to a file', Symbol::Tick ], $this->table->getFooter() );
	}

	#[Test]
	public function itCreatesRowsFromFetchedItemsCountAndAccentedCharacterAction(): void {
		$rows = $this->table->fetchedItemsCount( 5 )->getBuiltRows( 'test' );

		$this->assertCount( 1, $rows );
		$this->assertSame( Symbol::Green->value, (string) $rows['fetch']['Status'] );
		$this->assertSame( 'No. of test Fetched', $rows['fetch']['Action'] );
		$this->assertSame( 5, $rows['fetch']['Details'] );

		$this->assertSame(
			Symbol::Red->value,
			(string) $this->table->fetchedItemsCount( 0 )->getBuiltRows( 'test' )['fetch']['Status']
		);

		$rows = $this->table->accentedCharacters( 'escaped' )->getBuiltRows( 'test' );

		$this->assertCount( 2, $rows );

		$this->assertSame( Symbol::Green->value, (string) $rows['accents']['Status'] );
		$this->assertSame( 'Accented Characters', $rows['accents']['Action'] );
		$this->assertSame( 'escaped', $rows['accents']['Details'] );

		$rows = $this->table->accentedCharacters( null )->getBuiltRows( 'test' );

		$this->assertCount( 2, $rows );

		$this->assertSame( 0, $rows['fetch']['Details'] );

		$this->assertSame( Symbol::Yellow->value, (string) $rows['accents']['Status'] );
		$this->assertSame( 'Accented Characters', $rows['accents']['Action'] );
		$this->assertSame( 'N/A', $rows['accents']['Details'] );
	}

	#[Test]
	#[DataProvider( 'provideCollectionDetails' )]
	public function itCreatesRowsFromCollectionDetails(
		IndexKey $indexKey,
		int $count,
		array $KeysInfo,
		?array $indexInfo = null
	): void {
		$rows = $this->table->collectedUsing( $indexKey )->getBuiltRows( 'test' );

		$this->assertCount( $count, $rows );

		[$status, $action, $details] = $KeysInfo;

		$this->assertSame( $status, (string) $rows['keys']['Status'] );
		$this->assertSame( "Collection Key{$action}", $rows['keys']['Action'] );
		$this->assertSame( $details, $rows['keys']['Details'] );

		if ( ! $indexInfo ) {
			$this->assertArrayNotHasKey( 'index', $rows );

			return;
		}

		[$status, $details] = $indexInfo;

		$this->assertSame( $status, (string) $rows['index']['Status'] );
		$this->assertSame( 'Indexed by Value of', $rows['index']['Action'] );
		$this->assertSame( $details, $rows['index']['Details'] );
	}

	public static function provideCollectionDetails(): array {
		return [
			[ new IndexKey( null, [ 'a' ] ), 1, [ Symbol::Green->value, '', '"a"' ] ],
			[ new IndexKey( null, [ 'a', 'b' ], [ 'no-effect', 'when-null' ] ), 1, [ Symbol::Green->value, 's', '"a" | "b"' ] ],
			[
				new IndexKey( 'b', [ 'a' ] ),
				2,
				[ Symbol::Green->value, '', '"a"' ],
				[ Symbol::NotAllowed->value, 'N/A (Possible option is: "a")' ],
			],
			[
				new IndexKey( 'b', [ 'a' ], [ 'a' ] ),
				2,
				[ Symbol::Green->value, '', '"a"' ],
				[ Symbol::NotAllowed->value, 'N/A' ],
			],
			[
				new IndexKey( 'b', [ 'a', 'c' ] ),
				2,
				[ Symbol::Green->value, 's', '"a" | "c"' ],
				[ Symbol::NotAllowed->value, 'N/A (Possible option is one of: "a" | "c")' ],
			],
			[
				new IndexKey( 'b', [ 'a', 'c' ], [ 'a' ] ),
				2,
				[ Symbol::Green->value, 's', '"a" | "c"' ],
				[ Symbol::NotAllowed->value, 'N/A (Possible option is: "c")' ],
			],
			[
				new IndexKey( 'b', [ 'a', 'c', 'd' ], [ 'a', 'd' ] ),
				2,
				[ Symbol::Green->value, 's', '"a" | "c" | "d"' ],
				[ Symbol::NotAllowed->value, 'N/A (Possible option is: "c")' ],
			],
			[
				new IndexKey( 'b', [ 'a', 'c', 'd', 'e' ], [ 'a', 'd' ] ),
				2,
				[ Symbol::Green->value, 's', '"a" | "c" | "d" | "e"' ],
				[ Symbol::NotAllowed->value, 'N/A (Possible option is one of: "c" | "e")' ],
			],
			[
				new IndexKey( 'b', [ 'a', 'b', 'c' ] ),
				2,
				[ Symbol::Green->value, 's', '"a" | "b" | "c"' ],
				[ Symbol::Green->value, 'b' ],
			],
		];
	}

	#[Test]
	#[DataProvider( 'provideCacheDetails' )]
	public function itCreatesRowsFromCacheDetails(
		array $args,
		array $pathInfo,
		array $byteInfo,
		array $footerDetails,
		?array $consoleColors = null
	): void {
		$rows            = $this->table->withCacheDetails( ...$args )->getBuiltRows( 'test' );
		$success         = null !== $consoleColors;
		$statusCode      = $success ? 0 : 1;
		$consoleColors ??= [ 'red', '#eee' ];

		$this->assertCount( 2, $rows );
		$this->assertSame( $consoleColors ?? [ 'green', 'black' ], $this->table->getConsoleColors() );
		$this->assertSame( $footerDetails, $this->table->getFooter() );
		$this->assertSame( $success, $this->table->isSuccess() );
		$this->assertSame( $statusCode, $this->table->getStatusCode() );

		$this->assertSame( $pathInfo[0], (string) $rows['path']['Status'] );
		$this->assertSame( 'Cache Filepath', $rows['path']['Action'] );
		$this->assertSame( $pathInfo[1] ?? $args[0], $rows['path']['Details'] );

		$this->assertSame( $byteInfo[0], (string) $rows['byte']['Status'] );
		$this->assertSame( 'Total Bytes Written', $rows['byte']['Action'] );
		$this->assertSame( $byteInfo[1] ?? $args[2], $rows['byte']['Details'] );
	}

	public static function provideCacheDetails(): array {
		return [
			[
				[ 'both.pass', 'content', 7 ],
				[ Symbol::Green->value ],
				[ Symbol::Green->value ],
				[ 'Parsed and cached to a file: both.pass', Symbol::Tick ],
				[ 'green', 'black' ],
			],
			[
				[ 'content.fail', false, 7 ],
				[ Symbol::Red->value ],
				[ Symbol::Green->value ],
				[ 'Could not parse but cached to a file: content.fail', Symbol::Cross ],
			],
			[
				[ 'byte.fail', 'content', false ],
				[ Symbol::Green->value ],
				[ Symbol::Red->value, 0 ],
				[ 'Parsed but could not cache to a file: byte.fail', Symbol::Cross ],
			],
			[
				[ 'both.fail', false, false ],
				[ Symbol::Red->value ],
				[ Symbol::Red->value, 0 ],
				[ 'Could not parse and could not cache to a file: both.fail', Symbol::Cross ],
			],
			[
				[ 'byte.technically.pass', false, 0 ],
				[ Symbol::Red->value ],
				[ Symbol::Green->value ],
				[ 'Could not parse but cached to a file: byte.technically.pass', Symbol::Cross ],
			],
		];
	}

	#[Test]
	#[DataProvider( 'provideCommandRanOutput' )]
	public function itEnsuresCommandRanIsOutputted(
		Symbol $symbol,
		?array $cacheDetails = null,
		string $top = PHP_EOL,
		string $bottom = PHP_EOL
	): void {
		$table = new ScrapedTable( $output = new BufferedOutput() );

		$cacheDetails && $table->withCacheDetails( ...$cacheDetails );

		$table->forCommand( 'test' )->writeCommandRan( $top, $bottom );

		$this->assertSame( "{$top}{$symbol->value}  Ran command: \"test\" {$bottom}" . PHP_EOL, $output->fetch() );
	}

	public static function provideCommandRanOutput(): array {
		return [
			[ Symbol::Tick ],
			[ Symbol::Tick, [ 'test.pass', 'valid content', 10 ], 'top', 'bottom' ],
			[ Symbol::Cross, [ 'test.fail', false, 0 ] ],
			[ Symbol::Cross, [ 'test.fail', 'valid content', false ] ],
		];
	}

	#[Test]
	#[DataProvider( 'provideFooterOutput' )]
	public function itEnsuresFooterIsOutputted(
		string $footerMsg,
		?array $cacheDetails = null,
		string $top = PHP_EOL,
		string $bottom = PHP_EOL,
	): void {
		$table = new ScrapedTable( $output = new BufferedOutput() );

		$cacheDetails && $table->withCacheDetails( ...$cacheDetails );

		$table->writeFooter( $top, $bottom );

		$this->assertSame( "{$top}{$footerMsg}{$bottom}" . PHP_EOL, $output->fetch() );
	}

	public static function provideFooterOutput(): array {
		return [
			[ 'Skipped parsing and caching to a file' ],
			[ 'Parsed and cached to a file: test.pass', [ 'test.pass', 'content', 10 ], 'top', 'bottom' ],
		];
	}

	#[Test]
	#[DataProvider( 'provideOutputVerbosity' )]
	public function itOutputsBasedOnVerbosityLevel(
		int $outputVerbosity,
		int $writeVerbosity,
		bool $isEmptyOutput
	): void {
		( new ScrapedTable( $output = new BufferedOutput( $outputVerbosity ) ) )
			->writeWhenVerbose( 'test', $writeVerbosity );

		$this->assertSame( $isEmptyOutput, empty( $output->fetch() ) );
	}

	public static function provideOutputVerbosity(): array {
		return [
			[ OutputInterface::VERBOSITY_NORMAL, OutputInterface::VERBOSITY_DEBUG, true ],
			[ OutputInterface::VERBOSITY_VERY_VERBOSE, OutputInterface::VERBOSITY_VERBOSE, false ],
			[ OutputInterface::VERBOSITY_NORMAL, OutputInterface::VERBOSITY_SILENT, false ],
		];
	}

	#[Test]
	public function itPolyfillsWhenTableCachingIsDisabled(): void {
		( new ScrapedTable( $output = new BufferedOutput(), cachingDisabled: false ) )->write( 'test' );

		$this->assertStringNotContainsString( 'Total Bytes Written', $written = $output->fetch() );
		$this->assertStringNotContainsString( 'Cache Filepath', $written );

		( new ScrapedTable( $output = new BufferedOutput(), cachingDisabled: true ) )->write( 'test' );

		$this->assertStringContainsString( 'Total Bytes Written', $written = $output->fetch() );
		$this->assertStringContainsString( 'Cache Filepath', $written );

		preg_match_all( '/skipped/', $written, $matches );

		$this->assertCount( 2, $matches[0] );
	}

	#[Test]
	public function itEnsuresBuiltRowsAreOutputted(): void {
		( new ScrapedTable( $output = new BufferedOutput() ) )
			->forCommand( 'PHP CLI' )
			->accentedCharacters( 'transliterated' )
			->collectedUsing( new IndexKey( 'd', [ 'a', 'b', 'c' ], [ 'a' ] ) )
			->fetchedItemsCount( 10 )
			->withCacheDetails( 'pass.test', 'content', 7 )
			->write( 'test' )
			->writeFooter()
			->writeCommandRan();

		$written = $output->fetch();

		$this->assertStringContainsString( 'No. of test Fetched', $written );
		$this->assertStringContainsString( '10', $written );
		$this->assertStringContainsString( 'Collection Keys', $written );
		$this->assertStringContainsString( '"a" | "b" | "c"', $written );
		$this->assertStringContainsString( 'Indexed by Value of', $written );
		$this->assertStringContainsString( 'N/A (Possible option is one of: "b" | "c")', $written );
		$this->assertStringContainsString( 'Accented Characters', $written );
		$this->assertStringContainsString( 'transliterated', $written );
		$this->assertStringContainsString( 'Total Bytes Written', $written );
		$this->assertStringContainsString( '7', $written );
		$this->assertStringContainsString( 'Cache Filepath', $written );
		$this->assertStringContainsString( 'pass.test', $written );
		$this->assertStringContainsString( Symbol::Tick->value . '  Ran command: "PHP CLI"', $written );

		preg_match_all( '/Ran command/', $written, $matches );

		$this->assertStringNotContainsString( 'Parse', $written, 'Footer output when table is not written.' );
		$this->assertCount( 1, $matches[0], 'Either as table footer or output when table is not written.' );
	}
}
