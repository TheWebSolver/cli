<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Integration\Scraper;

use Symfony\Component\Console\Helper\Table;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;

class TableActionBuilder {
	final public const ITEMS_SEPARATOR = '|';
	final public const HEADERS         = [ 'Status', 'Action', 'Details' ];
	final public const NOT_AVAILABLE   = 'N/A';
	final public const ROW_FETCH       = 'fetch';
	final public const ROW_KEYS        = 'keys';
	final public const ROW_INDEX       = 'index';
	final public const ROW_ACCENTS     = 'accents';
	final public const ROW_BYTES       = 'byte';
	final public const ROW_PATH        = 'path';
	final public const TABLE_ACTIONS   = [
		self::ROW_FETCH   => 'No. of %s Fetched',
		self::ROW_KEYS    => 'Collection Keys',
		self::ROW_INDEX   => 'Indexed by Value of',
		self::ROW_ACCENTS => 'Accented Characters',
		self::ROW_BYTES   => 'Total Bytes Written',
		self::ROW_PATH    => 'Cache Filepath',
	];

	/** @var array<string,Symbol> */
	private array $symbols;
	/** @var array<string,string|int|null> */
	private array $actions;
	/** @var mixed[] */
	private array $symbolCellOptions;
	/** @var array{first:string,last:string} */
	private array $rowIndices;

	/** @param array<string> $array */
	public static function convertToString( array $array, string $wrapper = '"' ): string {
		return "$wrapper" . implode( "$wrapper " . self::ITEMS_SEPARATOR . " $wrapper", $array ) . "$wrapper";
	}

	/** @param non-empty-array<string,string> $rows action ID as key and title as value. */
	public function __construct( private array $rows = self::TABLE_ACTIONS ) {
		$this->rowIndices = [
			'first' => array_key_first( $rows ),
			'last'  => array_key_last( $rows ),
		];
	}

	public function withSymbol( string $rowIndex, Symbol $symbol ): self {
		$this->symbols[ $rowIndex ] = $symbol;

		return $this;
	}

	public function withAction( string $rowIndex, string|int|null $actionValue ): self {
		$this->actions[ $rowIndex ] = $actionValue;

		return $this;
	}

	/** @param mixed[] $options */
	public function withSymbolCellOptions( array $options ): self {
		$this->symbolCellOptions = $options;

		return $this;
	}

	/** @return array<string,array{Status:TableCell,Action:string,Details:string|int}> */
	public function build( Table $table, string $context ): array {
		$built = [];

		foreach ( $this->rows as $rowIndex => $Action ) {
			if ( is_null( $Details = $this->getActionDetailsBy( $rowIndex ) ) ) {
				continue;
			}

			[$Action, $symbol] = $this->getNormalizedDetailAndSymbolOf( $rowIndex, $Action, $context );

			is_string( $Details ) && empty( $Details ) && $this->asNotAvailable( $Details, $symbol );

			$Status = new TableCell( $symbol, $this->symbolCellOptions );

			$this->actionToSingularIfOnlyOne( $Details, $rowIndex, $Action );

			$table->addRow( $row = compact( self::HEADERS ) );

			$this->shouldAddSeparatorFor( $rowIndex ) && $table->addRow( new TableSeparator() );

			$built[ $rowIndex ] = $row;
		}

		return $built;
	}

	protected function actionToSingularIfOnlyOne( string|int $details, string $rowIndex, string &$action ): void {
		self::ROW_KEYS === $rowIndex
			&& ! str_contains( (string) $details, self::ITEMS_SEPARATOR )
			&& $action = substr( $action, 0, -1 );
	}

	private function getActionDetailsBy( string $rowIndex ): string|int|null {
		return $this->actions[ $rowIndex ] ?? null;
	}

	/** @return array{0:string,1:string} */
	private function getNormalizedDetailAndSymbolOf( string $rowIndex, string $action, string $context ): array {
		return [
			self::ROW_FETCH === $rowIndex ? sprintf( $action, $context ) : $action,
			( $this->symbols[ $rowIndex ] ?? Symbol::Green )->value,
		];
	}

	private function shouldAddSeparatorFor( string $rowIndex ): bool {
		return $this->rowIndices['first'] === $rowIndex || $this->rowIndices['last'] !== $rowIndex;
	}

	/** @param-out string $details */
	private function asNotAvailable( string|int &$details, string &$symbol ): void {
		$details = self::NOT_AVAILABLE;
		$symbol  = Symbol::Yellow->value;
	}
}
