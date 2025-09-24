<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture;

use Closure;
use TheWebSolver\Codegarage\Cli\Attribute\Command;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\ScrapedTable;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\TableConsole;

#[Command( 'test', 'rows', 'test table rows' )]
class TableConsoleCommand extends TableConsole {
	public const WRITE_BEFORE_TABLE_ROWS = 'Before table rows return';

	public const CONTEXT  = 'test';
	public const DEFAULTS = [
		'indexKey'    => null,
		'datasetKeys' => null,
		'accent'      => null,
	];

	public const TABLE_ROWS = [
		'data'  => [ [ 'one', 'two', 'three' ] ],
		'cache' => [
			'bytes'   => 0,
			'content' => '[["one","two","three"]]',
			'path'    => 'test.path',
		],
	];

	private ScrapedTable $output__table;

	public function __construct(
		protected array $defaults = self::DEFAULTS,
		protected array $tableRows = self::TABLE_ROWS
	) {
		parent::__construct();

		$this->tableRows['cache']['bytes'] = strlen( $this->tableRows['cache']['content'] );
	}

	protected function getTableContextForOutput(): string {
		return self::CONTEXT;
	}

	protected function getInputDefaultsForOutput(): array {
		$this->defaults['datasetKeys'] = $this->getInputAttribute()->getSuggestion()['collection-key'] ?? null;

		return $this->defaults;
	}

	protected function getTableRows( bool $ignoreCache, ?Closure $outputWriter ): array {
		$outputWriter && $outputWriter( self::WRITE_BEFORE_TABLE_ROWS );

		$keys   = $this->getInputValue()['datasetKeys'] ?? $this->getInputDefaultsForOutput()['datasetKeys'] ?? [];
		$update = [];

		foreach ( $this->tableRows['data'] as $rowIndex => $rowValue ) {
			if ( $keys ) {
				$rowValue = array_combine( $keys, $rowValue );
				$key      = $rowValue[ $this->getInputValue()['indexKey'] ] ?? null;
			}

			$update[ $key ?? $rowIndex ] = $rowValue;
		}

		$this->tableRows['data']             = $update;
		$this->tableRows['cache']['content'] = $json = json_encode( $update );
		// $this->tableRows['cache']['bytes']   = strlen( $json );

		return $this->tableRows;
	}

	public function getTable(): ScrapedTable {
		return $this->output__table;
	}

	protected function getOutputTable( OutputInterface $output, bool $cachingDisabled ): ScrapedTable {
		return $this->output__table = parent::getOutputTable( $output, $cachingDisabled );
	}
}
