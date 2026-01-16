<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Integration\Scraper;

use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\Helper\Table;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Cli\Helper\TableActionBuilder;

class ScrapedTable extends Table {
	private TableCellStyle $centeredCellStyle;
	/** @var array{0:string,1:string} bg and fg. */
	private array $consoleColor = [ 'green', 'black' ];
	/** @var array{0:string,1:Symbol} msg and symbol. */
	private array $footer       = [ 'Skipped parsing and caching to a file', Symbol::Tick ];
	private bool $tableRendered = false;
	private bool $success       = true;

	public function __construct(
		private readonly OutputInterface $output,
		private readonly bool $cachingDisabled = false,
		private string $commandName = '',
		private readonly TableActionBuilder $builder = new TableActionBuilder()
	) {
		parent::__construct( $output );

		$this->setStyle( 'box' )->registerCenteredCellStyle();
	}

	public function forCommand( string $name ): self {
		$this->commandName = $name;

		return $this;
	}

	public function collectedUsing( IndexKey $indexKey ): self {
		$keys = $indexKey->collection;

		$this->builder->withAction( TableRow::Keys, $this->builder->convertToString( $keys ) );

		if ( ! $indexKey->value || in_array( $indexKey->value, $keys, true ) ) {
			$this->builder->withAction( TableRow::Index, $indexKey->value );

			return $this;
		}

		return $this->withRegisteredAllowedIndexKeyActionAndSymbol( $indexKey );
	}

	public function fetchedItemsCount( int $count ): self {
		$this->builder->withAction( TableRow::Fetch, $count );

		! ! $count || $this->builder->withSymbol( TableRow::Fetch, Symbol::Red );

		return $this;
	}

	/** @param null|non-empty-string $action */
	public function accentedCharacters( ?string $action ): self {
		$this->builder->withAction( TableRow::Accent, $action ?? TableActionBuilder::NOT_AVAILABLE );

		! ! $action || $this->builder->withSymbol( TableRow::Accent, Symbol::Yellow );

		return $this;
	}

	public function withCacheDetails( string $path, string|false $content, int|false $bytes ): self {
		$realpath      = realpath( $path ) ?: $path;
		$hasContent    = false !== $content;
		$parseAndCache = $this->getRegisteredContentParsedAction( $realpath, $hasContent )
			. ' ' . $this->getRegisteredContentCachedAction( $bytes, $hasContent );

		( ! $hasContent || false === $bytes ) && $this->success = false;
		false === $this->success && $this->consoleColor         = [ 'red', '#eee' ];

		$this->footer = [ "{$parseAndCache}: {$realpath}", $this->success ? Symbol::Tick : Symbol::Cross ];

		return $this;
	}

	/** @return array<string,array{Status:TableCell,Action:string,Details:string|int}> */
	public function getBuiltRows( string $context ): array {
		return $this->builder->build( $this, $this->getNormalizedTableRows( $context ) );
	}

	/** @return array{0:string,1:string} bg and fg. */
	public function getConsoleColors(): array {
		return $this->consoleColor;
	}

	/** @return array{0:string,1:Symbol} msg and symbol. */
	public function getFooter(): array {
		return $this->footer;
	}

	public function isSuccess(): bool {
		return $this->success;
	}

	public function getStatusCode(): int {
		return $this->isSuccess() ? Console::SUCCESS : Console::FAILURE;
	}

	public function writeWhenVerbose( string $context, int $level = OutputInterface::VERBOSITY_VERBOSE ): self {
		return $this->output->getVerbosity() < $level ? $this : $this->write( $context );
	}

	public function write( string $context ): self {
		$this->tableRendered = true;

		$this->polyfillWhenCachingIsDisabled();
		$this->getBuiltRows( $context );
		$this->output->writeln( PHP_EOL );
		$this->setFooterTitle( $this->getFormattedCommandName() )->render();
		$this->output->writeln( PHP_EOL );

		return $this;
	}

	/** Writes footer info only if table is not written. */
	public function writeFooter( string $topPad = PHP_EOL, string $bottomPad = PHP_EOL ): self {
		$this->tableRendered || $this->output->writeln( "{$topPad}{$this->getFooter()[0]}$bottomPad" );

		return $this;
	}

	/** Writes footer info only if table is not written. */
	public function writeCommandRan( string $topPad = PHP_EOL, string $bottomPad = PHP_EOL ): self {
		$this->tableRendered || $this->output->writeln( $this->getFormattedCommandName( $topPad, $bottomPad ) );

		return $this;
	}

	private function getRegisteredContentParsedAction( string $path, bool $hasContent ): string {
		[$symbol, $details] = $hasContent ? [ Symbol::Green, 'Parsed' ] : [ Symbol::Red, 'Could not parse' ];

		$this->builder->withAction( TableRow::Path, $path );
		$this->builder->withSymbol( TableRow::Path, $symbol );

		return $details;
	}

	private function getRegisteredContentCachedAction( int|false $bytes, bool $hasContent ): string {
		[$symbol, $details] = false !== $bytes
			? [ Symbol::Green, ( $hasContent ? 'and' : 'but' ) . ' cached to a file' ]
			: [ Symbol::Red, ( $hasContent ? 'but' : 'and' ) . ' could not cache to a file' ];

		$this->builder->withAction( TableRow::Byte, $bytes ?: 0 );
		$this->builder->withSymbol( TableRow::Byte, $symbol );

		return $details;
	}

	private function withRegisteredAllowedIndexKeyActionAndSymbol( IndexKey $indexKey ): self {
		$NA = TableActionBuilder::NOT_AVAILABLE;

		if ( ! $allowedKeys = $indexKey->withOnlyAllowed()->collection ) {
			$status = $NA;
		} else {
			$oneOf  = count( $allowedKeys ) === 1 ? '' : ' one of';
			$status = "{$NA} (Possible option is{$oneOf}: {$this->builder->convertToString( $allowedKeys )})";
		}

		$this->builder->withAction( TableRow::Index, $status );
		$this->builder->withSymbol( TableRow::Index, Symbol::NotAllowed );

		return $this;
	}

	private function getFormattedCommandName( string $topPad = '', string $bottomPad = '' ): string {
		[$bg, $fg] = $this->getConsoleColors();
		$symbol    = $this->getFooter()[1];
		$format    = "<bg={$bg};fg={$fg};options=bold>";

		return "{$topPad}{$symbol->value} {$format} Ran command: \"{$this->commandName}\" </>{$bottomPad}";
	}

	private function polyfillWhenCachingIsDisabled(): bool {
		if ( ! $this->cachingDisabled ) {
			return false;
		}

		$this->builder
			->withAction( TableRow::Path, 'skipped' )
			->withSymbol( TableRow::Path, Symbol::Yellow )
			->withAction( TableRow::Byte, 'skipped' )
			->withSymbol( TableRow::Byte, Symbol::Yellow );

		return true;
	}

	private function registerCenteredCellStyle(): self {
		$this->centeredCellStyle = new TableCellStyle( [ 'align' => 'center' ] );

		$this->builder->withSymbolCellOptions( [ 'style' => $this->centeredCellStyle ] );

		return $this;
	}

	/** @return non-empty-array<string,string> */
	private function getNormalizedTableRows( string $context ): array {
		$rows                          = array_column( TableRow::cases(), column_key: 'value', index_key: 'name' );
		$rows[ TableRow::Fetch->name ] = str_replace( 'items', $context, $rows[ TableRow::Fetch->name ], $context );
		$collectionKeysDetails         = $this->builder->getActionDetailsBy( TableRow::Keys->name );

		! $collectionKeysDetails
			|| str_contains( (string) $collectionKeysDetails, TableActionBuilder::ITEMS_SEPARATOR )
			|| $rows[ TableRow::Keys->name ] = substr( $rows[ TableRow::Keys->name ], offset: 0, length: -1 );

		return $rows;
	}
}
