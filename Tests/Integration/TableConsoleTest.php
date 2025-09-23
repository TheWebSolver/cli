<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Integration;

use Closure;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Application;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Input\ArrayInput;
use TheWebSolver\Codegarage\Cli\Data\Positional;
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
		$this->assertSame( $expectedRows['cache']['bytes'], $bytes['Details'] );
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
			public function __construct( private array $keys, private array $disallowed ) {
				parent::__construct();

				$keys && $this->tableRows['data'] = $keys;
			}

			protected function getDisallowedIndexKeys(): array {
				return $this->disallowed;
			}

			protected function outputParsedContent( array $content, ?ConsoleSectionOutput $vvv ): void {
				if ( ! $this->keys ) {
					TestCase::assertEmpty( $content );
				} else {
					TestCase::assertSame( array_combine( $this->keys, $this->keys ), $content );
				}
			}
		};

		$app->add( $command::start( constructorArgs: compact( 'keys', 'disallowed' ) ) );

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
	}

	#[Test]
	public function itEnsuresCollectionKeysAreUsedEitherFromInputOrSuggested(): void {
		$tester = new ApplicationTester( $app = new Application() );

		$app->setAutoExit( false );

		$command = new #[Command( 'from', 'suggestion', 'Keys from attribute' )]
		#[Positional( 'collection-key', suggestedValues: [ 'suggestOne', 'suggestTwo', 'suggestThree' ] )]
		class() extends TableConsoleCommand {
			protected function outputParsedContent( array $content, ?ConsoleSectionOutput $vvv ): void {
				TestCase::assertSame(
					[
						'suggestOne'   => 'one',
						'suggestTwo'   => 'two',
						'suggestThree' => 'three',
					],
					$content
				);
			}
		};

		$app->add( $command::start() );
		$tester->run( [ 'command' => 'from:suggestion' ] );

		$command = new #[Command( 'from', 'input', 'Keys from input' )] class() extends TableConsoleCommand {
			protected function outputParsedContent( array $content, ?ConsoleSectionOutput $vvv ): void {
				TestCase::assertSame(
					[
						'keyOne'   => 'one',
						'keyTwo'   => 'two',
						'keyThree' => 'three',
					],
					$content
				);
			}
		};

		$app->add( $command = $command::start() );

		$tester->run(
			[
				'command'        => 'from:input',
				'collection-key' => [ 'keyOne', 'keyTwo', 'keyThree' ],
			]
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
}
