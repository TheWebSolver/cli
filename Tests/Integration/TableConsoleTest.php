<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Integration;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Application;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
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
use TheWebSolver\Codegarage\Test\Fixture\TableConsoleCommand;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\TableActionBuilder;

class TableConsoleTest extends TestCase {
	#[Test]
	public function itOutputsRetrievedTableRowsToConsole(): void {
		$output        = $this->createMock( ConsoleOutputInterface::class );
		$section       = $this->createMock( ConsoleSectionOutput::class );
		$expectedCache = TableConsoleCommand::TABLE_ROWS['cache']['content'];

		// We cannot determine no. of times below method is invoked (as it may get invoked by app also).
		$output->method( 'getVerbosity' )->willReturn( OutputInterface::VERBOSITY_DEBUG );
		$output->expects( $this->exactly( 2 ) )->method( 'section' )->willReturn( $section );

		$section->expects( $this->exactly( 4 ) )->method( 'addContent' );
		$section->expects( $this->once() )->method( 'getContent' )->willReturn( $expectedCache );
		$section->expects( $writeln = $this->exactly( 2 ) )->method( 'writeln' )->willReturnCallback(
			function ( ...$args ) use ( $writeln, $expectedCache ): void {
				if ( 1 === $writeln->numberOfInvocations() ) {
					$this->assertSame( TableConsoleCommand::WRITE_BEFORE_TABLE_ROWS, $args[0] );
				} else {
					$this->assertSame( $expectedCache, $args[0] );
				}
			}
		);

		( $app = new Application() )->add( $command = TableConsoleCommand::start() );

		$command->run( new ArrayInput( [ 'command' => 'test:rows' ], $app->getDefinition() ), $output );
	}

	#[Test]
	public function itOutputsTableRows(): void {
		$tester = new ApplicationTester( $app = new Application() );

		$app->setAutoExit( false );
		$app->setCatchExceptions( false );

		$defaults           = TableConsoleCommand::DEFAULTS;
		$defaults['accent'] = 'escaped';

		$app->add( $command = TableConsoleCommand::start( constructorArgs: compact( 'defaults' ) ) );

		$tester->run( [ 'test:rows' ] );

		$expectedRows = TableConsoleCommand::TABLE_ROWS;

		$this->assertStringContainsString( 'Ran command: "test:rows"', $tester->getDisplay() );
		$this->assertStringContainsString(
			sprintf( 'Parsed and cached to a file: %s', $expectedRows['cache']['path'] ),
			$tester->getDisplay()
		);

		$rows = $command->getTable()->getBuiltRows( 'separate test' );

		foreach ( array_keys( TableActionBuilder::TABLE_ACTIONS ) as $expectedBuiltKey ) {
			if ( in_array( $expectedBuiltKey, [ 'keys', 'index' ], true ) ) {
				$this->assertArrayNotHasKey( $expectedBuiltKey, $rows );
			} else {
				$this->assertArrayHasKey( $expectedBuiltKey, $rows );
			}
		}

		$fetch = array_shift( $rows );

		$this->assertSame( 'No. of separate test Fetched', $fetch['Action'] );
		$this->assertSame( count( $expectedRows['data'] ), $fetch['Details'] );

		$accentedCharacters = array_shift( $rows );

		$this->assertSame( 'Accented Characters', $accentedCharacters['Action'] );
		$this->assertSame( 'escaped', $accentedCharacters['Details'] );
		$this->assertSame( Symbol::Green->value, (string) $accentedCharacters['Status'] );

		$bytes = array_shift( $rows );

		$this->assertSame( 'Total Bytes Written', $bytes['Action'] );
		$this->assertSame( strlen( $expectedRows['cache']['content'] ), $bytes['Details'] );
		$this->assertSame( Symbol::Green->value, (string) $bytes['Status'] );

		$path = array_shift( $rows );

		$this->assertSame( 'Cache Filepath', $path['Action'] );
		$this->assertSame( $expectedRows['cache']['path'], $path['Details'] );
		$this->assertSame( Symbol::Green->value, (string) $path['Status'] );

		$this->assertEmpty( $rows );
	}

	#[Test]
	#[DataProviderExternal( IndexKeyTest::class, 'provideCollectablesAndDisallowedKeys' )]
	public function itThrowsExceptionWhenIndexKeyMismatch(
		?string $key,
		array $keys,
		array $disallowed,
		?string $thrown = null
	): void {
		$tester = new ApplicationTester( $app = new Application() );

		$app->setAutoExit( false );
		$app->setCatchExceptions( false );

		$command = new #[Command( 'test', 'key', 'Test key' )] class( $keys, $disallowed ) extends TableConsoleCommand {
			public array $content;

			public function __construct( private array $keys, private array $disallowed ) {
				$rows = self::TABLE_ROWS;
				// Using collection keys as data for array_combine to work with equal length.
				$rows['data'] = [ $keys ];

				parent::__construct( tableRows: $rows );
			}

			protected function getDisallowedIndexKeys(): array {
				return $this->disallowed;
			}

			protected function outputParsedContent( array $content, ?ConsoleSectionOutput $vvv ): void {
				$this->content = $content;
			}
		};

		$app->add( $command = $command::start( constructorArgs: compact( 'keys', 'disallowed' ) ) );

		if ( $thrown ) {
			$this->expectException( OutOfBoundsException::class );
			$this->expectExceptionMessage( sprintf( IndexKey::INVALID, $key ) . ' ' . $thrown );
		}

		$tester->run(
			[
				'command'        => 'test:key',
				'collection-key' => $keys,
				'--with-key'     => $key ?: '',
			]
		);

		$content = $command->content;

		$this->assertCount( 1, $content );

		$actualValue = current( $content );

		$this->assertSame( $keys, array_keys( $actualValue ) );
		$this->assertSame( $keys, array_values( $actualValue ) );

		$this->assertSame( $key ?: 0, key( $content ), 'Key and value is array_combine of $keys.' );
		$this->assertSame( $key ?: 0, $actualValue[ $key ] ?? 0, 'Key is $actualValue indexed by $key.' );
	}

	#[Test]
	public function itEnsuresCollectionKeysAreUsedEitherFromInputOrSuggested(): void {
		$tester = new ApplicationTester( $app = new Application() );

		$app->setAutoExit( false );

		$collectionFromAttribute = new #[Command( 'from', 'suggestion', 'Keys from attribute' )]
		#[Positional( 'collection-key', suggestedValues: [ 'suggestOne', 'suggestTwo', 'suggestThree' ] )]
		class() extends TableConsoleCommand {
			public array $content;

			protected function outputParsedContent( array $content, ?ConsoleSectionOutput $vvv ): void {
				$this->content = $content;
			}
		};

		$app->add( $collectionFromAttribute = $collectionFromAttribute::start() );
		$tester->run( [ 'command' => 'from:suggestion' ] );

		$this->assertSame(
			[
				'suggestOne'   => 'one',
				'suggestTwo'   => 'two',
				'suggestThree' => 'three',
			],
			$collectionFromAttribute->content[0]
		);

		$collectionFromInputArgument = new #[Command( 'from', 'input', 'Keys from input' )] class() extends TableConsoleCommand {
			public array $content;

			protected function outputParsedContent( array $content, ?ConsoleSectionOutput $vvv ): void {
				$this->content = $content;
			}
		};

		$app->add( $collectionFromInputArgument = $collectionFromInputArgument::start() );

		$tester->run(
			[
				'command'        => 'from:input',
				'collection-key' => [ 'keyOne', 'keyTwo', 'keyThree' ],
			]
		);

		$this->assertSame(
			[
				'keyOne'   => 'one',
				'keyTwo'   => 'two',
				'keyThree' => 'three',
			],
			$collectionFromInputArgument->content[0]
		);

		$inputArgumentOverridesAttribute = new #[Command( 'arg', 'overrides', 'Collection keys from args overrides' )]
		#[Positional( 'collection-key', suggestedValues: [ 'AttrOne', 'AttrTwo', 'AttrThree' ] )]
		#[Associative( 'with-key', shortcut: [ 'i', 'k' ] )]
		class() extends TableConsoleCommand {
			public array $content;

			protected function outputParsedContent( array $content, ?ConsoleSectionOutput $vvv ): void {
				$this->content = $content;
			}
		};

		$app->add( $inputArgumentOverridesAttribute = $inputArgumentOverridesAttribute::start() );

		$tester->run(
			[
				'command'        => 'arg:overrides',
				'collection-key' => [ 'overrideOne', 'overrideTwo', 'overrideThree' ],
			]
		);

		$this->assertSame(
			[
				'overrideOne'   => 'one',
				'overrideTwo'   => 'two',
				'overrideThree' => 'three',
			],
			$inputArgumentOverridesAttribute->content[0]
		);

		$app->setCatchExceptions( false );

		// Only key provided, collection keys not provided.
		$this->expectException( OutOfBoundsException::class );
		$this->expectExceptionMessage( sprintf( IndexKey::INVALID, 'keyOne' ) . ' ' . IndexKey::EMPTY_COLLECTABLE );

		$tester->run(
			[
				'command'    => 'from:input',
				'--with-key' => 'keyOne',
				// 'collection-key' => [ 'keyOne', 'keyTwo', 'keyThree' ],
			]
		);
	}

	#[Test]
	public function itMapsCollectionKeysAndIndexByValueOfAssignedIndexKey(): void {
		$tester  = new ApplicationTester( $app = new Application() );
		$command = new #[Command( 'index', 'shortcut', 'Uses index key provided with shortcut key' )]
		#[Positional( 'collection-key', suggestedValues: [ 'AttrOne', 'AttrTwo', 'AttrThree' ] )]
		#[Associative( 'with-key', shortcut: [ 'i', 'k' ] )]
		class() extends TableConsoleCommand {
			public array $content;

			protected function outputParsedContent( array $content, ?ConsoleSectionOutput $vvv ): void {
				$this->content = $content;
			}
		};

		$app->setAutoExit( false );
		$app->add( $command = $command::start() );

		$tester->run(
			[
				'command' => 'index:shortcut',
				'-k'      => 'AttrTwo',
			]
		);

		$this->assertSame(
			[
				'two' => [
					'AttrOne'   => 'one',
					'AttrTwo'   => 'two',
					'AttrThree' => 'three',
				],
			],
			$command->content
		);
	}
}
