<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Integration;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\Console\Application;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Attribute\Command;
use Symfony\Component\Console\Output\OutputInterface;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\IndexKey;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\TableRow;
use TheWebSolver\Codegarage\Test\Fixture\TableConsoleCommand;

class TableConsoleTest extends TestCase {
	private const EXPECTED_JSON = '{"two":{"a":"one","b":"two","c":"three"}}';

	private ApplicationTester $tester;
	private Application $app;

	protected function setUp(): void {
		$this->app    = new Application();
		$this->tester = new ApplicationTester( $this->app );

		$this->app->setAutoExit( false );
		$this->app->setCatchExceptions( false );
	}

	protected function tearDown(): void {
		unset( $this->app, $this->tester );
	}

	#[Test]
	public function commandFixtureSetup(): TableConsoleCommand {
		$command = new #[Command( 'test', 'command', 'Test inputs' )]
		#[Positional( 'collection-key', suggestedValues: [ 'x', 'y', 'z' ] )]
		#[Associative( 'with-key', default: 'c' )]
		class() extends TableConsoleCommand {};

		$this->app->add( $command = new $command() );
		$this->tester->run(
			[
				'command'        => 'test:command',
				'collection-key' => [ 'a', 'b', 'c' ],
				'--with-key'     => 'b',
				'--accent'       => 'accentAction',
				'--to-filename'  => 'file',
				'--extension'    => 'format',
			]
		);

		$this->assertSame(
			[
				'accent'      => 'accentAction',
				'datasetKeys' => [ 'a', 'b', 'c' ],
				'indexKey'    => 'b',
				'filename'    => 'file',
				'extension'   => 'format',
			],
			$command->getInputValue()
		);

		$this->assertSame(
			[
				'indexKey'    => 'c',
				'datasetKeys' => [ 'x', 'y', 'z' ],
				'accent'      => null,
			],
			$command->getInputDefaultsForOutput()
		);

		$this->assertSame(
			[
				'two' => [
					'a' => 'one',
					'b' => 'two',
					'c' => 'three',
				],
			],
			$command->tableRows['data']
		);

		$this->assertSame( 'file.format', $command->tableRows['cache']['path'] );
		$this->assertSame( self::EXPECTED_JSON, $command->tableRows['cache']['content'] );

		$this->tester->run(
			[
				'command' => 'test:command',
				'-k'      => 'x',
			]
		);

		$this->assertSame( 'x', $command->getInputValue()['indexKey'], 'Index key provided using shortcut' );

		return $command;
	}

	#[Test]
	#[Depends( 'commandFixtureSetup' )]
	public function itMocksOutputSectionIsInvokedWithTableRowsDataAndWritesToCli( TableConsoleCommand $command ): void {
		$output  = $this->createMock( ConsoleOutputInterface::class );
		$section = $this->createMock( ConsoleSectionOutput::class );

		// We cannot determine no. of times below method is invoked (as it may get invoked by app also).
		$output->method( 'getVerbosity' )->willReturn( OutputInterface::VERBOSITY_DEBUG );
		$output->expects( $this->exactly( 2 ) )->method( 'section' )->willReturn( $section );

		$section->expects( $this->exactly( 4 ) )->method( 'addContent' );
		$section->expects( $this->once() )->method( 'getContent' )->willReturn( self::EXPECTED_JSON );
		$section->expects( $invokedCount = $this->exactly( 2 ) )->method( 'writeln' )->willReturnCallback(
			function ( ...$args ) use ( $invokedCount ): void {
				if ( 1 === $invokedCount->numberOfInvocations() ) {
					$this->assertSame( TableConsoleCommand::WRITE_BEFORE_TABLE_ROWS, $args[0] );
				} else {
					$this->assertSame( self::EXPECTED_JSON, $args[0] );
				}
			}
		);

		$this->app->add( $fixture = new $command() );
		$fixture->run( new ArrayInput( [ 'command' => 'test:command' ] ), $output );
	}

	#[Test]
	#[Depends( 'commandFixtureSetup' )]
	public function itBuildsTableActionBuilderWithComputedData( TableConsoleCommand $command ): void {
		$this->app->add( $fixture = new $command() );
		$this->tester->run(
			[
				'command'       => 'test:command',
				'--to-filename' => 'table-output',
			]
		);

		$this->assertStringContainsString( 'Ran command: "test:command"', $this->tester->getDisplay() );
		$this->assertStringContainsString(
			'Parsed and cached to a file: table-output',
			$this->tester->getDisplay()
		);

		$rows = $fixture->scrapedTable->getBuiltRows( 'table rows' );

		foreach ( array_column( TableRow::cases(), 'name' ) as $expectedBuiltKey ) {
			$this->assertArrayHasKey( $expectedBuiltKey, $rows );
		}

		$fetch = array_shift( $rows );

		$this->assertSame( 'No. of table rows Fetched', $fetch['Action'] );
		$this->assertSame( 1, $fetch['Details'] );

		$keys = array_shift( $rows );

		$this->assertSame( 'Collection Keys', $keys['Action'] );
		$this->assertSame( Symbol::Green->value, (string) $keys['Status'] );
		$this->assertSame( '"x" | "y" | "z"', $keys['Details'], 'Default value provided in attribute' );

		$key = array_shift( $rows );

		$this->assertSame( 'Indexed by Value of', $key['Action'] );
		$this->assertSame( Symbol::NotAllowed->value, (string) $key['Status'] );
		$this->assertSame(
			'N/A (Possible option is one of: "x" | "y" | "z")',
			$key['Details'],
			'Collection keys provided in attribute used. Consequently, default indexKey as "c" is not allowed.'
		);

		$accentedCharacters = array_shift( $rows );

		$this->assertSame( 'Accented Characters', $accentedCharacters['Action'] );
		$this->assertSame( Symbol::Yellow->value, (string) $accentedCharacters['Status'] );
		$this->assertSame( 'N/A', $accentedCharacters['Details'], 'Action not provided in CLI input.' );

		$bytes = array_shift( $rows );

		$this->assertSame( 'Total Bytes Written', $bytes['Action'] );
		$this->assertSame( Symbol::Green->value, (string) $bytes['Status'] );
		$this->assertSame(
			strlen( '[{"x":"one","y":"two","z":"three"}]' ),
			$bytes['Details'],
			'Table rows is never indexed by value of disallowed key. Corresponding values are indexed by collection keys.'
		);

		$path = array_shift( $rows );

		$this->assertSame( 'Cache Filepath', $path['Action'] );
		$this->assertSame( Symbol::Green->value, (string) $path['Status'] );
		$this->assertSame(
			'table-output.json',
			$path['Details'],
			'Filename used and when extension not provided in CLI input, default attribute\'s "json" used.'
		);

		$this->assertEmpty( $rows, 'All built table rows are asserted.' );

		$this->tearDown();
		$this->setUp();

		$this->app->add( $command = new $command() );
		$this->tester->run(
			[
				'command'        => 'test:command',
				'collection-key' => [ 'a', 'b', 'c' ],
				'--accent'       => 'translit',
			]
		);

		[
			TableRow::Keys->name    => $keys,
			TableRow::Index->name   => $key,
			TableRow::Accent->name => $accentedCharacters,
			TableRow::Byte->name    => $bytes,
		] = $command->scrapedTable->getBuiltRows( 'with collection keys and accent action from CLI input' );

		$this->assertSame( '"a" | "b" | "c"', $keys['Details'], 'Collection keys from CLI input.' );
		$this->assertSame( 'c', $key['Details'], "Index key from input option's default value." );
		$this->assertSame( Symbol::Green->value, (string) $key['Status'] );
		$this->assertSame( 'translit', $accentedCharacters['Details'], 'Accented characters action from CLI input.' );
		$this->assertSame( Symbol::Green->value, (string) $accentedCharacters['Status'] );
		$this->assertSame(
			strlen( '{"three":{"a":"one","b":"two","c":"three"}}' ),
			$bytes['Details'],
			'Table rows are indexed by value of index-key. Table row values are indexed by collection keys.'
		);
	}

	#[Test]
	#[DataProviderExternal( IndexKeyTest::class, 'provideCollectablesAndDisallowedKeys' )]
	public function itThrowsExceptionWhenIndexKeyMismatch(
		?string $key,
		array $keys,
		array $disallowed,
		?string $thrown = null
	): void {
		$command = new #[Command( 'test', 'key', 'Test key' )] class( $keys, $disallowed ) extends TableConsoleCommand {
			public function __construct( private array $keys, private array $disallowed ) {
				// Using collection keys as data for array_combine in fixture class to work with equal length.
				parent::__construct( tableRows: [ ...self::TABLE_ROWS, ...[ 'data' => [ $keys ] ] ] );
			}

			protected function getDisallowedIndexKeys(): array {
				return $this->disallowed;
			}
		};

		$this->app->add( $command = new $command( $keys, $disallowed ) );

		if ( $thrown ) {
			$this->expectException( OutOfBoundsException::class );
			$this->expectExceptionMessage( sprintf( IndexKey::INVALID, $key ) . ' ' . $thrown );
		}

		$this->tester->run(
			[
				'command'        => 'test:key',
				'collection-key' => $keys,
				'--with-key'     => $key ?: '',
			]
		);

		$this->assertCount( 1, $command->tableRows['data'] );

		$actualValue = current( $command->tableRows['data'] );

		$this->assertSame( $keys, array_keys( $actualValue ) );
		$this->assertSame( $keys, array_values( $actualValue ) );

		$this->assertSame( $key ?: 0, key( $command->tableRows['data'] ), 'Key and value is array_combine of $keys.' );
		$this->assertSame( $key ?: 0, $actualValue[ $key ] ?? 0, 'Key is $actualValue indexed by $key.' );
	}

	#[Test]
	#[Depends( 'commandFixtureSetup' )]
	#[DataProvider( 'provideIndexAndCollectionKeysForValidation' )]
	public function itUsesSuggestedOrUserProvidedCollectionKeysForIndexKeyValidation(
		string $indexKey,
		?array $collectionKey,
		TableConsoleCommand $command
	): void {
		$input = [
			'command'    => 'test:command',
			'--with-key' => $indexKey,
		];

		$collectionKey && $input['collection-key'] = $collectionKey;

		$this->app->add( $suggested = new $command() );
		$this->tester->run( $input );

		$this->assertSame( $indexKey, $suggested->getInputValue()['indexKey'] );
	}

	public static function provideIndexAndCollectionKeysForValidation(): array {
		return [
			'Validated against attribute when no argument passed' => [ 'y', null ],
			'Validated against "1", "2", and "3"' => [ '2', [ '1', '2', '3' ] ],
			'No validation against CLI input or suggested values if is default value' => [ 'c', null ],
		];
	}

	#[Test]
	public function itThrowsExceptionWhenIndexKeyCouldNotBeValidatedAgainstSuggestedCollectionKey(): void {
		$this->expectException( OutOfBoundsException::class );
		// Only "--with-key" option passed without providing "collection-key". Validation fails
		// for "1" as "collection-key" input does not have suggested values to validate against.
		$this->expectExceptionMessage( sprintf( IndexKey::INVALID, '1' ) . ' ' . IndexKey::EMPTY_COLLECTABLE );

		$this->app->add( new TableConsoleCommand() );
		$this->tester->run(
			[
				'command'        => 'test:rows',
				'--with-key'     => '1',
				// 'collection-key' => [ '1', '2', '3' ,
			]
		);
	}
}
