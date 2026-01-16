<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use UnitEnum;
use Symfony\Component\Console\Helper\Table;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;

class TableActionBuilder {
	final public const ITEMS_SEPARATOR = '|';
	final public const NOT_AVAILABLE   = 'N/A';
	final public const HEADERS         = [ 'Status', 'Action', 'Details' ];

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

	public function withSymbol( UnitEnum $action, Symbol $symbol ): self {
		$this->symbols[ $action->name ] = $symbol;

		return $this;
	}

	public function withAction( UnitEnum $action, string|int|null $actionValue ): self {
		$this->actions[ $action->name ] = $actionValue;

		return $this;
	}

	/** @param mixed[] $options */
	public function withSymbolCellOptions( array $options ): self {
		$this->symbolCellOptions = $options;

		return $this;
	}

	public function getActionDetailsBy( string $unitEnumCaseName ): string|int|null {
		return $this->actions[ $unitEnumCaseName ] ?? null;
	}

	/**
	 * @param non-empty-array<string,string> $rows action ID as key and title as value.
	 * @return array<string,array{Status:TableCell,Action:string,Details:string|int}>
	 */
	public function build( Table $table, array $rows ): array {
		$built            = [];
		$this->rowIndices = [
			'first' => array_key_first( $rows ),
			'last'  => array_key_last( $rows ),
		];

		$table->setHeaders( self::HEADERS );

		foreach ( $rows as $unitEnumCaseName => $Action ) {
			if ( is_null( $Details = $this->getActionDetailsBy( $unitEnumCaseName ) ) ) {
				continue;
			}

			$symbol = ( $this->symbols[ $unitEnumCaseName ] ?? Symbol::Green )->value;

			is_string( $Details ) && empty( $Details ) && $this->asNotAvailable( $Details, $symbol );

			$Status = new TableCell( $symbol, $this->symbolCellOptions );

			$table->addRow( $row = compact( self::HEADERS ) );

			$this->shouldAddSeparatorFor( $unitEnumCaseName ) && $table->addRow( new TableSeparator() );

			$built[ $unitEnumCaseName ] = $row;
		}

		return $built;
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
