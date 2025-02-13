<?php
/**
 * This file is part of the CLI (this) library but is not auto-loaded by default.
 *
 * This compliments implementing package to perform task in the following order:
 * - discovers the implementing package root path,
 * - requires composer's "autoload.php" file, and
 * - requires the "config.php" file to load commands.
 *
 * The package using this library must:
 * - first require this file,
 * - extend the "Bootstrap" (this) class, and
 * - finally bootstrap commands by performing required action.
 *
 * @package TheWebSolver\Codegarage\Cli
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Closure;
use LogicException;
use TheWebSolver\Codegarage\Cli\CommandLoader;
use TheWebSolver\Codegarage\Container\Container;

abstract class Bootstrap {
	final private function __construct( private string $packageRootPath = '' ) {}

	/**
	 * @param Closure(CommandLoader, array<string,mixed>, string): void $action
	 * @throws LogicException When target (main) package directory path cannot be discovered.
	 *                        When "config.php" file with directories to be scanned not provided.
	 */
	public static function commands( Closure $action ): void {
		( $bootstrap = new static() )->discoverPackageRootPath();

		$action( ...array( ...$bootstrap->configure(), $bootstrap->packageRootPath ) );
	}

	/**
	 * Gets the CLI package root path built on top of this CLI library.
	 *
	 * For eg: package named "resolver" has a CLI feature. It has
	 * a separate package named "resolver-cli". Then, it should
	 * return root directory path of a "resolver-cli" package.
	 *
	 * @return string Usually, the **\_\_DIR\_\_** constant value.
	 */
	abstract protected function cliPackagePath(): string;

	/**
	 * Gets root path of target (main) package using CLI package during development.
	 *
	 * For eg: package named "handler" uses "resolver" and "resolver-cli" package.
	 * Then, it should return root directory path of the main "handler" package.
	 *
	 * @internal Most of the time, "handler" and "resolver" is same (that is being built).
	 *           This is not a user facing API and must only work during development.
	 */
	protected function relativeToLocalSymlinkPath(): ?string {
		return null;
	}

	/** Gets root path of target (main) package using CLI package running as: "vendor/bin/iso". */
	protected function relativeToVendorBinPath(): ?string {
		return ( $binPath = ( $GLOBALS['_composer_bin_dir'] ?? null ) ) && is_string( $binPath )
			? dirname( $binPath, 2 )
			: null;
	}

	/** Gets root path of target (main) package using CLI package if running as standalone composer package. */
	private function discoveredPath(): ?string {
		if ( ! realpath( $cliPackagePath = $this->cliPackagePath() ) ) {
			return null;
		}

		// Usually, "$cliPackagePath" is: "path/to/($packageRoot)/vendor/{$vendorName}/{$packageName}".
		// We'll start discovering the package path from the "$vendorName" directory and its parents.
		$currentRoot = dirname( $cliPackagePath );
		$packageRoot = null;

		do {
			$composerJson = $currentRoot . DIRECTORY_SEPARATOR . 'composer.json';

			// If composer.json file found in current directory, must be the package root. Else keep propagating.
			( is_file( $composerJson ) && $packageRoot = $currentRoot ) || $currentRoot = dirname( $currentRoot );
		} while ( null === $packageRoot && DIRECTORY_SEPARATOR !== $currentRoot );

		return $packageRoot;
	}

	/** @throws LogicException When main package's root patch cannot be discovered. */
	private function discoverPackageRootPath(): void {
		$this->packageRootPath = $this->relativeToVendorBinPath()
			?? $this->relativeToLocalSymlinkPath()
			?? $this->discoveredPath()
			?? throw new LogicException( 'Impossible to discover package root path. Are you using composer?' );
	}

	/**
	 * @return array{0:CommandLoader,1:array<string,mixed>}
	 * @throws LogicException When package "config.php" file not found.
	 */
	private function configure( string $slash = DIRECTORY_SEPARATOR ): array {
		require_once "{$this->packageRootPath}{$slash}vendor{$slash}autoload.php";

		$hasPackageConfig = is_readable( $configFile = "{$this->packageRootPath}{$slash}config.php" );

		$hasPackageConfig || $configFile = "{$this->cliPackagePath()}{$slash}config.php";

		if ( ! is_readable( $configFile ) ) {
			throw new LogicException(
				'Configuration file not provided.' . PHP_EOL .
				'CLI Package created using this library must have a "config.php" file that provides ' .
				'commands to be loaded.'
			);
		}

		/**
		 * @var array{
		 *   commandLoader?:class-string<CommandLoader>,
		 *   directory?:    array<int,array{path:string,namespace:string}>,
		 *   subDirectory?: array<string,int|int[]>
		 * }
		 */
		$config = require_once $configFile;

		/** @var class-string<CommandLoader> */
		$commandLoaderClass = $config['commandLoader'] ?? CommandLoader::class;
		$commandLoader      = $commandLoaderClass::with( Container::boot() );
		$packageRootPath    = $hasPackageConfig ? $this->packageRootPath : $this->cliPackagePath();

		foreach ( $config['directory'] ?? array() as ['path' => $dirname, 'namespace' => $namespace] ) {
			$commandLoader->inDirectory( "{$packageRootPath}{$slash}{$dirname}", $namespace );
		}

		foreach ( $config['subDirectory'] ?? array() as $subDirectoryName => $depth ) {
			/** @disregard P1013 Undefined method -- If sub-directory given, must be "SubDirectoryAware" */
			$commandLoader->usingSubDirectory( $subDirectoryName, ...$depth );
		}

		return array( $commandLoader, $config );
	}
}
