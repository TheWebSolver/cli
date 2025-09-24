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
		'data'  => [ 'one', 'two', 'three' ],
		'cache' => [
			'bytes'   => 11,
			'content' => '["one", "two", "three"]',
			'path'    => 'test.path',
		],
	];

	private ScrapedTable $output__table;

	public function __construct(
		protected array $defaults = self::DEFAULTS,
		protected array $tableRows = self::TABLE_ROWS
	) {
		parent::__construct();
	}

	protected function getTableContextForOutput(): string {
		return self::CONTEXT;
	}

	protected function getInputDefaultsForOutput(): array {
		return $this->defaults;
	}

	protected function getTableRows( bool $ignoreCache, ?Closure $outputWriter ): array {
		$outputWriter && $outputWriter( self::WRITE_BEFORE_TABLE_ROWS );

		if ( $keys = $this->getInputValue()['datasetKeys'] ) {
			$this->tableRows['data']             = $data = array_combine( $keys, $this->tableRows['data'] );
			$this->tableRows['cache']['content'] = json_encode( $data );
		}

		return $this->tableRows;
	}

	public function getTable(): ScrapedTable {
		return $this->output__table;
	}

	protected function getOutputTable( OutputInterface $output, bool $cachingDisabled ): ScrapedTable {
		return $this->output__table = parent::getOutputTable( $output, $cachingDisabled );
	}
}
