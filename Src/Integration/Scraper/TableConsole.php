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
	/** @var list<string> */
	private array $datasetIndicesFromArgument      = [];
	private ?string $validatedIndexKeyFromArgument = null;

	abstract protected function isCachingDisabled(): bool;
	/** @return array<TTableRowDataType> */
	abstract protected function getParsedContent( bool $ignoreCache, ?Closure $outputWriter = null ): array;

	abstract protected function setAccentOperationTypeFromInput( string $action ): bool;

	abstract protected function getTitleForOutput(): string;
	abstract protected function getTableContextForOutput(): string;
	/** @return non-empty-string|null */
	abstract protected function getAccentOperationTypeForOutput(): ?string;
	/** @return array{0:?string,1:list<string>} */
	abstract protected function getIndicesSourceForOutput(): array;

	/** @return string[] */
	protected function disallowedIndexKeys(): array {
		return [];
	}

	/**
	 * @param array<TTableRowDataType> $content
	 * @return array{0:string,1:string|false,2:int|false} The cache path, parsed content, bytes written
	 *                                                    and unicode escape status.
	 */
	abstract protected function cacheWithResourceDetailsFromInput(
		array $content,
		string $fileName,
		string $fileFormat
	): array;

	protected function configure() {
		parent::configure();

		$this->hasInputAttribute()
			|| $this->setInputAttribute( InputAttribute::from( static::class )->register()->parse() );
	}

	protected function initialize( InputInterface $input, OutputInterface $output ) {
		$this->datasetIndicesFromArgument = $input->getArgument( 'collection-key' ) ?: [];
	}

	/** @return list<string> */
	protected function getRowDatasetMappedIndicesFromInput(): array {
		return $this->datasetIndicesFromArgument;
	}

	protected function getRowDatasetIndexKeyFromInput(): ?string {
		return $this->validatedIndexKeyFromArgument;
	}

	/**
	 * Gets the Positional Attribute "collection-key"'s suggested values as allowed keys.
	 *
	 * Inheriting class may override this method to provide allowed keys directly.
	 *
	 * @param ?list<string> $argv
	 * @return string[]
	 */
	protected function getCollectionKeysFromInput( ?array $argv ): array {
		return ( $given = $this->getInputAttribute()->getSuggestion()['collection-key'] )
			? CommandSubscriber::inputSuggestedValues( $given, $argv )
			: [];
	}

	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$content = $this->scrapeAndParseContent( $input, $output );
		$table   = $this->createTableFor( $output, rowsCount: count( $content ) );

		if ( $vvv = $this->getOutputSection( $output ) ) {
			$this->outputParsedContent( $content, $vvv );
			$vvv->writeln( $vvv->getContent() );
		}

		$this->isCachingDisabled() || $table->withCacheDetails( ...$this->formatAndCache( $content, $input ) );

		return $table->writeWhenVerbose( $this->getTableContextForOutput() )
			->writeFooter()->writeCommandRan()->getStatusCode();
	}

	/**
	 * @param mixed[] $content
	 * @return array{0:string,1:string|false,2:int|false} The cache path, parsed content, and bytes written.
	 */
	private function formatAndCache( array $content, InputInterface $input ): array {
		return $this->cacheWithResourceDetailsFromInput(
			$content,
			fileName: (string) $input->getOption( 'to-filename' ),
			fileFormat: (string) ( $input->getOption( 'format' ) ?: 'json' )
		);
	}

	private function getOutputSection( OutputInterface $o, ?int $verbosity = null ): ?ConsoleSectionOutput {
		return $o instanceof ConsoleOutputInterface &&
			( $verbosity ?? OutputInterface::VERBOSITY_DEBUG ) <= $o->getVerbosity() ? $o->section() : null;
	}

	/** @param mixed[] $content */
	private function outputParsedContent( array $content, ConsoleSectionOutput $vvv ): void {
		$vvv->addContent( self::LONG_SEPARATOR_LINE );
		$vvv->addContent( "List of {$this->getTableContextForOutput()}:" );
		$vvv->addContent( self::LONG_SEPARATOR_LINE );
		$vvv->addContent( json_encode( $content ) ?: '' );
	}

	/** @throws OutOfBoundsException When key cannot be inferred. */
	protected function validatedIndexKeyFromArgument( InputInterface $input ): ?string {
		return ( new IndexKey(
			value: (string) $input->getOption( 'with-key' ),
			collection: $this->getCollectionKeysFromInput( $input instanceof ArgvInput ? $input->getRawTokens( strip: true ) : null ),
			disallowed: $this->disallowedIndexKeys()
		) )->validated()->value;
	}

	/** @return array<array-key,mixed> */
	private function scrapeAndParseContent( InputInterface $input, OutputInterface $output ): array {
		$this->setAccentOperationTypeFromInput( (string) $input->getOption( 'accent' ) );

		$this->validatedIndexKeyFromArgument = $this->validatedIndexKeyFromArgument( $input );
		$vv                                  = $this->getOutputSection( $output, OutputInterface::VERBOSITY_VERY_VERBOSE );

		return $this->getParsedContent(
			ignoreCache: true === $input->getOption( 'force' ),
			outputWriter: $vv ? $vv->writeln( ... ) : null
		);
	}

	private function createTableFor( OutputInterface $output, int $rowsCount ): ScrapedTable {
		[$indexKey, $datasetIndices] = $this->getIndicesSourceForOutput();

		return ( new ScrapedTable( $output, $this->isCachingDisabled() ) )
			->setHeaderTitle( $this->getTitleForOutput() )
			->forCommand( $this->getName() ?? '' )
			->accentedCharacters( $this->getAccentOperationTypeForOutput() )
			->collectedUsing( new IndexKey( $indexKey, $datasetIndices, $this->disallowedIndexKeys() ) )
			->fetchedItemsCount( $rowsCount );
	}
}
