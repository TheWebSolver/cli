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
use TheWebSolver\Codegarage\Cli\Event\CommandSubscriber;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\IndexKey;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\ScrapedTable;

/** @template TTableRowDataType */
#[Positional( 'collection-key', desc: 'Items to collect from scraped content', isVariadic: true )]
#[Associative( 'with-key', desc: 'Collection key to use as index', shortcut: [ 'i', 'k' ] )]
#[Associative( 'to-filename', desc: 'The filename (without extension) to write cached content to', shortcut: 'r' )]
#[Associative( 'extension', desc: 'Filename extension to save cache to.', shortcut: 'x', default: 'json' )]
#[Flag( 'show', desc: 'Display parsed data in console' )]
#[Associative( 'accent', desc: 'Handle accented characters in parsed content', isOptional: false )]
#[Flag( 'force', desc: 'Invalidate already cached data and scrape again from source' )]
abstract class TableConsole extends Console {
	/**
	 * @var array{
	 *  indexKey   : ?string,
	 *  datasetKeys: ?non-empty-list<string>,
	 *  accent     : ?non-empty-string,
	 *  filename   : string,
	 *  extension  : string
	 * }
	 */
	private array $inputValue;

	/**
	 * Gets parsed table raws.
	 *
	 * @return array{
	 *  data : array<iterable<array-key,TTableRowDataType>>,
	 *  cache: ?array{path:string,bytes:int|false,content:non-empty-string|false}
	 * } The `cache` value must be `null` when table rows data is not cached.
	 */
	abstract protected function getTableRows( bool $ignoreCache, ?Closure $outputWriter ): array;

	/** @return array{indexKey:?string,datasetKeys:?non-empty-list<string>,accent:?non-empty-string} */
	abstract protected function getInputDefaultsForOutput(): array;
	abstract protected function getTableContextForOutput(): string;

	protected function initialize( InputInterface $input, OutputInterface $output ) {
		$this->inputValue['accent']      = ( $accent = $input->getOption( 'accent' ) ) ? (string) $accent : null;
		$this->inputValue['datasetKeys'] = ! empty( $keys = $input->getArgument( 'collection-key' ) ) ? $keys : null;
		$this->inputValue['indexKey']    = $this->getValidatedIndexKeyFromInput( $input );
		$this->inputValue['filename']    = (string) $input->getOption( 'to-filename' );
		$this->inputValue['extension']   = (string) ( $input->getOption( 'extension' ) );
	}

	/**
	 * @return array{
	 *  indexKey   : ?string,
	 *  datasetKeys: ?non-empty-list<string>,
	 *  accent     : ?non-empty-string,
	 *  filename   : string,
	 *  extension  : string
	 * }
	 */
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
		return ( $given = ( $this->getInputAttribute()->getSuggestion()['collection-key'] ?? false ) )
			? CommandSubscriber::inputSuggestedValues( $given, $argv )
			: [];
	}

	/**
	 * Gets index key after validating against collection keys.
	 *
	 * @throws OutOfBoundsException When index key cannot be verified against collection keys.
	 */
	protected function getValidatedIndexKeyFromInput( InputInterface $input ): ?string {
		$indexKey = (string) $input->getOption( 'with-key' );
		$keyInput = $this->getInputAttribute()->getInputBy( 'with-key', Associative::class );
		( $s = $keyInput?->shortcut ) && $this->stringFromShortcut( $indexKey, $input, (array) $s );

		if ( $keyInput?->default === $indexKey ) {
			return $indexKey;
		}

		$collection = $this->getUserProvidedCollectionKeys() ?? $this->getSuggestedCollectionKeys(
			argv: $input instanceof ArgvInput ? $input->getRawTokens( strip: true ) : null
		);

		return ( new IndexKey( $indexKey, $collection, $this->getDisallowedIndexKeys() ) )->validated()->value;
	}

	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$ignoreCache = true === $input->getOption( 'force' );
		$vv          = $this->getOutputSection( $output, OutputInterface::VERBOSITY_VERY_VERBOSE );
		$tableRows   = $this->getTableRows( $ignoreCache, outputWriter: $vv ? $vv->writeln( ... ) : null );

		$this->outputParsedContent( $tableRows['data'], vvv: $this->getOutputSection( $output ) );

		$cacheDetails = $tableRows['cache'];
		$table        = $this->createTableFor( $output, count( $tableRows['data'] ), cached: ! ! $cacheDetails );
		$context      = $this->getTableContextForOutput();

		! ! $cacheDetails && $table->withCacheDetails( ...$cacheDetails );

		return $table->writeWhenVerbose( $context )->writeFooter()->writeCommandRan()->getStatusCode();
	}

	final protected function getTableActionStatus( bool $cachingDisabled ): string {
		$actions                       = [ 'Scraped', 'parsed' ];
		$cachingDisabled || $actions[] = 'cached';
		$lastAction                    = array_pop( $actions );

		return implode( ', ', $actions ) . " and {$lastAction} " . $this->getTableContextForOutput();
	}

	/**
	 * Gets table helper instance to output scraped, parsed, and/or cached table rows.
	 *
	 * It is recommended to provide the table header title by the inheriting class
	 * when this method is overridden. Otherwise, no header title will be set.
	 */
	protected function getOutputTable( OutputInterface $output, bool $cachingDisabled ): ScrapedTable {
		return ( new ScrapedTable( $output, $cachingDisabled ) )
			->setHeaderTitle( $this->getTableActionStatus( $cachingDisabled ) );
	}

	/** @param mixed[] $content */
	protected function outputParsedContent( array $content, ?ConsoleSectionOutput $vvv ): void {
		if ( ! $vvv ) {
			return;
		}

		$vvv->addContent( self::LONG_SEPARATOR_LINE );
		$vvv->addContent( "List of {$this->getTableContextForOutput()}:" );
		$vvv->addContent( self::LONG_SEPARATOR_LINE );
		$vvv->addContent( json_encode( $content ) ?: '' );
		$vvv->writeln( $vvv->getContent() );
	}

	/** @param string[] $shortcuts */
	private function stringFromShortcut( string &$value, InputInterface $input, array $shortcuts ): void {
		str_starts_with( $value, needle: '=' )
			&& array_filter(
				array: array_map( static fn( string $s ): string => "-{$s}", $shortcuts ),
				callback: $input->getParameterOption( ... )
			) && $value = substr( $value, offset: 1 );
	}

	private function createTableFor( OutputInterface $output, int $rowsCount, bool $cached ): ScrapedTable {
		$input   = $this->getInputValue();
		$default = $this->getInputDefaultsForOutput();
		$table   = $this->getOutputTable( $output, ! $cached )
			->forCommand( $this->getName() ?? '' )
			->accentedCharacters( $input['accent'] ?? $default['accent'] )
			->fetchedItemsCount( $rowsCount );

		empty( $collection = $input['datasetKeys'] ?? $default['datasetKeys'] ) || $table->collectedUsing(
			new IndexKey( $input['indexKey'] ?? $default['indexKey'], $collection, $this->getDisallowedIndexKeys() )
		);

		return $table;
	}
}
