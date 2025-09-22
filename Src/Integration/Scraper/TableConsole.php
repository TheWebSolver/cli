<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Integration\Scraper;

use Closure;
use OutOfBoundsException;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use Symfony\Component\Console\Input\ArgvInput;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;
use TheWebSolver\Codegarage\Cli\Event\CommandSubscriber;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\IndexKey;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\ScrapedTable;

/** @template TTableRowDataType */
#[Positional( 'collection-key', desc: 'Items to collect from scraped content', isVariadic: true )]
#[Associative( 'with-key', desc: 'Collection key to use as index' )]
#[Associative( 'to-filename', desc: 'The filename (without extension) to write cached content to', shortcut: 'r' )]
#[Associative( 'format', desc: 'Format to save cache to. Defaults to "json"', shortcut: 'x' )]
#[Flag( 'show', desc: 'Display parsed data in console' )]
#[Associative( 'accent', desc: 'Handle accented characters in parsed content', isOptional: false )]
#[Flag( 'force', desc: 'Invalidate already cached data and scrape again from source' )]
abstract class TableConsole extends Console {
	/** @var array{indexKey:?string,datasetKeys:?non-empty-list<string>,accent:?non-empty-string} */
	private array $inputValue;

	/** @return array<TTableRowDataType> */
	abstract protected function getTableRows( bool $ignoreCache, ?Closure $outputWriter = null ): array;

	/**
	 * Gets details after table rows as cached with given filename and file format.
	 *
	 * This method is only invoked when `$this->isCachingDisabled()` method returns `false`.
	 *
	 * @param array<TTableRowDataType> $tableRows
	 * @return array{path:string,bytes:int|false,content:non-empty-string|false}
	 */
	abstract protected function getCacheDetails( array $tableRows, string $fileName, string $fileFormat ): array;
	abstract protected function isCachingDisabled(): bool;

	/** @return array{indexKey:?string,datasetKeys:?non-empty-list<string>,accent:?non-empty-string} */
	abstract protected function getInputDefaultsForOutput(): array;
	abstract protected function getTableContextForOutput(): string;


	protected function configure() {
		parent::configure();

		$this->hasInputAttribute()
			|| $this->setInputAttribute( InputAttribute::from( static::class )->register()->parse() );
	}

	protected function initialize( InputInterface $input, OutputInterface $output ) {
		$this->inputValue['accent']      = ( $accent = $input->getOption( 'accent' ) ) ? (string) $accent : null;
		$this->inputValue['datasetKeys'] = ! empty( $keys = $input->getArgument( 'collection-key' ) ) ? $keys : null;
		$this->inputValue['indexKey']    = $this->getValidatedIndexKeyFromInput( $input );
	}

	/** @return array{indexKey:?string,datasetKeys:?non-empty-list<string>,accent:?non-empty-string} */
	protected function getInputValue(): array {
		return $this->inputValue;
	}

	/** @return ?non-empty-list<string> */
	final protected function getUserProvidedCollectionKeys(): ?array {
		$default      = $this->getInputAttribute()->getInputBy( 'collection-key', Positional::class )?->default;
		$userProvided = $this->getInputValue()['datasetKeys'];

		return $default === $userProvided ? null : $userProvided;
	}

	/**
	 * Gets subset of collection keys that are not allowed to be used as an index key.
	 *
	 * @return list<string>
	 */
	protected function getDisallowedIndexKeys(): array {
		return [];
	}

	/**
	 * Gets the Positional Attribute "collection-key"'s suggested values as collection keys.
	 *
	 * @param ?list<string> $argv
	 * @return string[]
	 */
	protected function getSuggestedCollectionKeys( ?array $argv ): array {
		return ( $given = $this->getInputAttribute()->getSuggestion()['collection-key'] )
			? CommandSubscriber::inputSuggestedValues( $given, $argv )
			: [];
	}

	/**
	 * Gets index key after validating against collection keys.
	 *
	 * @throws OutOfBoundsException When index key cannot be verified against collection keys.
	 *
	 * Inheriting class may override this method to validate index key.
	 */
	protected function getValidatedIndexKeyFromInput( InputInterface $input ): ?string {
		$indexKey   = (string) $input->getOption( 'with-key' );
		$default    = $this->getInputAttribute()->getInputBy( 'with-key', Associative::class )?->default;
		$collection = $this->getUserProvidedCollectionKeys() ?? $this->getSuggestedCollectionKeys(
			argv: $input instanceof ArgvInput ? $input->getRawTokens( strip: true ) : null
		);

		$key = new IndexKey( $indexKey, $collection, $this->getDisallowedIndexKeys() );

		return $default === $indexKey ? $key->value : $key->validated()->value;
	}

	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$ignoreCache = true === $input->getOption( 'force' );
		$vv          = $this->getOutputSection( $output, OutputInterface::VERBOSITY_VERY_VERBOSE );
		$tableRows   = $this->getTableRows( $ignoreCache, outputWriter: $vv ? $vv->writeln( ... ) : null );
		$table       = $this->createTableFor( $output, rowsCount: count( $tableRows ) );
		$context     = $this->getTableContextForOutput();

		if ( $vvv = $this->getOutputSection( $output ) ) {
			$this->outputParsedContent( $tableRows, $vvv );
			$vvv->writeln( $vvv->getContent() );
		}

		$this->isCachingDisabled() || $table->withCacheDetails(
			...$this->getCacheDetails(
				$tableRows,
				fileName: (string) $input->getOption( 'to-filename' ),
				fileFormat: (string) ( $input->getOption( 'format' ) )
			)
		);

		return $table->writeWhenVerbose( $context )->writeFooter()->writeCommandRan()->getStatusCode();
	}

	final protected function getTableActionStatus(): string {
		$actions                                 = [ 'Scraped', 'parsed' ];
		$this->isCachingDisabled() || $actions[] = 'cached';
		$lastAction                              = array_pop( $actions );

		return implode( ', ', $actions ) . " and {$lastAction} " . $this->getTableContextForOutput();
	}

	/**
	 * Gets table helper instance to output scraped, parsed, and/or cached table rows.
	 *
	 * It is recommended to provide the table header title by the inheriting class
	 * when this method is overridden. Otherwise, no header title will be set.
	 */
	protected function getOutputTable( OutputInterface $output ): ScrapedTable {
		return ( new ScrapedTable( $output, $this->isCachingDisabled() ) )
			->setHeaderTitle( $this->getTableActionStatus() );
	}

	private function getOutputSection(
		OutputInterface $output,
		int $verbosity = OutputInterface::VERBOSITY_DEBUG
	): ?ConsoleSectionOutput {
		return $output instanceof ConsoleOutputInterface && $verbosity <= $output->getVerbosity()
			? $output->section()
			: null;
	}

	/** @param mixed[] $content */
	private function outputParsedContent( array $content, ConsoleSectionOutput $vvv ): void {
		$vvv->addContent( self::LONG_SEPARATOR_LINE );
		$vvv->addContent( "List of {$this->getTableContextForOutput()}:" );
		$vvv->addContent( self::LONG_SEPARATOR_LINE );
		$vvv->addContent( json_encode( $content ) ?: '' );
	}

	private function createTableFor( OutputInterface $output, int $rowsCount ): ScrapedTable {
		$input   = $this->getInputValue();
		$default = $this->getInputDefaultsForOutput();
		$table   = $this->getOutputTable( $output )
			->forCommand( $this->getName() ?? '' )
			->accentedCharacters( $default['accent'] )
			->fetchedItemsCount( $rowsCount );

		empty( $collection = $input['datasetKeys'] ?? $default['datasetKeys'] ) || $table->collectedUsing(
			new IndexKey( $input['indexKey'] ?? $default['indexKey'], $collection, $this->getDisallowedIndexKeys() )
		);

		return $table;
	}
}
