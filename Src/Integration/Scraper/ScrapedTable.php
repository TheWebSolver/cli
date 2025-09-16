<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Integration\Scraper;

use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\Helper\Table;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Output\OutputInterface;

class ScrapedTable extends Table {
	private TableCellStyle $centeredCellStyle;
	/** @var array{0:string,1:string} bg and fg. */
	private array $consoleColor = [ 'green', 'black' ];
	/** @var array{0:string,1:Symbol} msg and symbol. */
	private array $footer       = [ 'Skipped parsing and caching to a file', Symbol::Tick ];
	private bool $tableRendered = false;
	private bool $success       = true;

	public function __construct(
		private OutputInterface $output,
		private bool $cachingDisabled = false,
		private string $commandName = '',
		public readonly TableActionBuilder $builder = new TableActionBuilder()
	) {
		parent::__construct( $output );

		$this->setStyle( 'box' )->setHeaders( TableActionBuilder::HEADERS )->registerCenteredCellStyle();
	}

	public function forCommand( string $name ): self {
		$this->commandName = $name;

		return $this;
	}

	/** @param non-empty-array<int,string> $keys */
	public function collectedUsing( array $keys, ?string $indexKey, string ...$disallowedIndexKeys ): self {
		$this->builder->withAction( TableActionBuilder::ROW_KEYS, $this->builder->convertToString( $keys ) );

		if ( ! $indexKey || in_array( $indexKey, $keys, true ) ) {
			$this->builder->withAction( TableActionBuilder::ROW_INDEX, $indexKey );

			return $this;
		}

		$this->builder->withSymbol( TableActionBuilder::ROW_INDEX, Symbol::NotAllowed );

		$disallowedIndexKeys
			&& $keys = array_filter( $keys, static fn( $key ) => ! in_array( $key, $disallowedIndexKeys, true ) );

		if ( ! $keys ) {
			$suffix = '';
		} else {
			$oneOf  = count( $keys ) === 1 ? '' : ' one of';
			$suffix = " (Possible option is{$oneOf}: {$this->builder->convertToString( $keys )})";
		}

		$this->builder->withAction( TableActionBuilder::ROW_INDEX, TableActionBuilder::NOT_AVAILABLE . "$suffix" );

		return $this;
	}

	public function fetchedItemsCount( int $count ): self {
		$this->builder->withAction( TableActionBuilder::ROW_FETCH, $count );

		return $this;
	}

	public function accentedCharacters( ?string $action ): self {
		$this->builder->withAction( TableActionBuilder::ROW_ACCENTS, $action ?? TableActionBuilder::NOT_AVAILABLE );

		! ! $action || $this->builder->withSymbol( TableActionBuilder::ROW_ACCENTS, Symbol::Yellow );

		return $this;
	}

	/** @param array{0:string,1:string|false,2:int|false} $data */
	public function withCacheDetails( ?array $data ): self {
		if ( null === $data ) {
			return $this;
		}

		[$cachePath, $content, $bytes] = $data;

		if ( false === $content ) {
			$this->builder
				->withSymbol( TableActionBuilder::ROW_PATH, Symbol::Red )
				->withAction( TableActionBuilder::ROW_PATH, $cachePath );

			$contentFooter = 'Could not extract';
			$this->success = false;
		} else {
			$cachePath     = realpath( $cachePath ) ?: $cachePath;
			$contentFooter = 'Extracted';

			$this->builder->withSymbol( TableActionBuilder::ROW_PATH, Symbol::Green );
			$this->builder->withAction( TableActionBuilder::ROW_PATH, $cachePath );
		}

		if ( false === $bytes ) {
			$this->builder
				->withSymbol( TableActionBuilder::ROW_BYTES, Symbol::Red )
				->withAction( TableActionBuilder::ROW_BYTES, 0 );

				$butOrAnd      = $this->success ? 'but' : 'and';
				$byteFooter    = "could not cache to a file: {$cachePath}";
				$this->success = false;
		} else {
			$this->builder
				->withSymbol( TableActionBuilder::ROW_BYTES, Symbol::Green )
				->withAction( TableActionBuilder::ROW_BYTES, $bytes );

			$butOrAnd   = $this->success ? 'and' : 'but';
			$byteFooter = "cached to a file: {$cachePath}";
		}

		! $this->success && $this->consoleColor = [ 'red', '#eee' ];

		$footerSymbol = $this->success ? Symbol::Tick : Symbol::Cross;
		$this->footer = [ "{$contentFooter} {$butOrAnd} {$byteFooter}", $footerSymbol ];

		return $this;
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

		$this->resetWhenCacheIsDisabled();

		$this->builder->build( $this, $context );

		$this->output->writeln( PHP_EOL );
		$this->setFooterTitle( $this->getFormattedCommandName() )->render();
		$this->output->writeln( PHP_EOL );

		return $this;
	}

	public function writeFooter( string $topPad = PHP_EOL, string $bottomPad = PHP_EOL ): self {
		$this->tableRendered || $this->output->writeln( "{$topPad}{$this->getFooter()[0]}$bottomPad" );

		return $this;
	}

	public function writeCommandRan( string $topPad = PHP_EOL, string $bottomPad = PHP_EOL ): self {
		$this->tableRendered || $this->output->writeln( $this->getFormattedCommandName( $topPad, $bottomPad ) );

		return $this;
	}

	private function getFormattedCommandName( string $topPad = '', string $bottomPad = '' ): string {
		[$bg, $fg] = $this->getConsoleColors();
		$symbol    = $this->getFooter()[1];
		$format    = "<bg={$bg};fg={$fg};options=bold>";

		return "{$topPad}{$symbol->value} {$format} Ran command: \"{$this->commandName}\" </>{$bottomPad}";
	}

	private function resetWhenCacheIsDisabled(): bool {
		if ( ! $this->cachingDisabled ) {
			return false;
		}

		$this->builder
			->withAction( TableActionBuilder::ROW_PATH, 'skipped' )
			->withSymbol( TableActionBuilder::ROW_PATH, Symbol::Yellow )
			->withAction( TableActionBuilder::ROW_BYTES, 'skipped' )
			->withSymbol( TableActionBuilder::ROW_BYTES, Symbol::Yellow );

		return true;
	}

	private function registerCenteredCellStyle(): self {
		$this->centeredCellStyle = new TableCellStyle( [ 'align' => 'center' ] );

		$this->builder->withSymbolCellOptions( [ 'style' => $this->centeredCellStyle ] );

		return $this;
	}
}
