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
 * - require this file, and
 * - bootstrap commands by performing required action.
 *
 * @package TheWebSolver\Codegarage\Cli
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Closure;
use RuntimeException;
use Composer\InstalledVersions;
use TheWebSolver\Codegarage\Cli\CommandLoader;

/**
 * @phpstan-type ConfigArray array{
 *   commandLoader :  CommandLoader,
 *   directory     ?: array<int,array{path:string,namespace:string}>,
 *   subDirectory  ?: array<string,int|int[]>,
 *   container     ?: \Psr\Container\ContainerInterface
 * }
 */
class Bootstrap {
	private bool $discoverable;
	public readonly string $cliPath;
	public readonly string $rootPath;
	/** @var ConfigArray */
	public readonly array $config;
	/** @var ?ConfigArray */
	public readonly ?array $cliConfig;

	private const INVALID_COMPOSER_PACKAGE     = 'Impossible to discover package root path. Are you using composer?';
	private const NON_DISCOVERABLE_CLI_PATH    = 'Cannot auto-discover CLI package. Override method "%s()" to provide CLI package path.';
	private const NON_DISCOVERABLE_CONFIG_PATH = "Configuration file not provided.\nCLI Package created using this library must 'have a \"config.php\" file at root path that provides commands to be loaded.";

	/** @param array{main?:string,cli?:string} $packages */
	final private function __construct( private array $packages = [] ) {
		$slash              = DIRECTORY_SEPARATOR;
		$installedVersions  = __DIR__ . "{$slash}vendor{$slash}composer{$slash}InstalledVersions.php";
		$this->discoverable = file_exists( $installedVersions );

		$this->discoverable && require_once $installedVersions;

		$this->rootPath = $this->discoverPackageRootPath();

		[$this->cliPath, $this->cliConfig, $this->config] = $this->configure();
	}

	/** @param ?string $path Uses `$this->cliPath` if not passed. */
	public function loadDirectories( ?string $path = null ): void {
		$path        ??= $this->cliPath;
		$commandLoader = $this->config['commandLoader'];

		foreach ( $this->config['directory'] ?? [] as ['path' => $dirname, 'namespace' => $namespace] ) {
			$commandLoader->inDirectory( $path . DIRECTORY_SEPARATOR . $dirname, $namespace );
		}

		foreach ( $this->config['subDirectory'] ?? [] as $subDirectoryName => $depth ) {
			/** @disregard P1013 Undefined method -- If sub-directory given, must be "SubDirectoryAware" */
			$commandLoader->usingSubDirectory( $subDirectoryName, ...$depth );
		}
	}

	/**
	 * Auto-loads project using composer autoloader, auto-discovers CLI configuration file and instantiates command loader.
	 *
	 * @param Closure(static): void           $action   The action must be performed by CLI package.
	 * @param ?array{cli:string,main?:string} $packages List of vendor's main & CLI package names.
	 * - `null` means no auto-discovery. The CLI package must extend this bootstrap class for manual directory path resolution.
	 * - `main` key/value pair in array is only required during development when main package requires CLI package as _symlink_
	 *     and CLI package does not extend this class or does not override `Bootstrap::relativeToLocalSymlinkPath()` method.
	 *
	 * ________________________________________________________________________________________________________
	 * **Neither providing array `main` key/value pair nor overriding `Bootstrap::relativeToLocalSymlinkPath()`
	 * will produce unexpected side-effect if local development uses _Symlink_ to require the CLI Package.**
	 * ________________________________________________________________________________________________________
	 *
	 * @throws RuntimeException When the CLI package directory path cannot be auto-discovered.\
	 *                          When **config.php** file not found for scanning directories.
	 * @example Usage
	 * CLI package binary file is in rootpath named: ***saral***
	 * ```php
	 * #!/usr/bin/env php
	 * use TheWebSolver\Codegarage\Cli\Bootstrap;
	 * use TheWebSolver\Codegarage\Cli\CommandLoader;
	 *
	 * require_once 'path/to/cliPackageRoot/vendor/thewebsolver/cli/bootstrap.php';
	 *
	 * Bootstrap::commands(usingCommandLoader(...), [
	 *  'main' => 'vendor/package',
	 *  'cli'  => 'vendor/package-cli',
	 * ]);
	 *
	 * function usingCommandLoader(Bootstrap $bootstrap) {
	 *  // Initialize PSR-11 container. Recommended to use initialized container in config file
	 *  // so that if "vendor/package" defines its own container instance, that gets injected.
	 *  // If no container found in config, this package fallback container may be used.
	 *  $container = $bootstrap->config['container'] ?? new \TheWebSolver\Codegarage\Cli\Container();
	 *
	 * // Defaults to "vendor/package-cli" root if not argument passed for defining root.
	 *  $bootstrap->loadDirectories();
	 *  $bootstrap->config['commandLoader']->load($container);
	 *  $container->get(Cli::class)->run();
	 * }
	 * ```
	 * ```sh
	 * # RECOMMENDED: CLI package lists binary file to composer's vendor/bin
	 * $ vendor/bin/saral namespace:command
	 *
	 * # NOT RECOMMENDED: Using binary file directly
	 * $ vendor/vendor/package-cli/saral namespace:command
	 * ```
	 */
	public static function commands( Closure $action, ?array $packages = null ): void {
		( $bootstrap = new static( $packages ?? [] ) );

		$action( $bootstrap );
	}

	/**
	 * Gets the CLI package root path built on top of this CLI library.
	 *
	 * For eg: package named ***resolver*** has a CLI feature. It has a CLI package named.
	 * ***resolver-cli***. Then, it should return root path of ***resolver-cli*** package.
	 *
	 * @return string Usually, the **\_\_DIR\_\_** constant value assuming bootstrap file is in package root.
	 * @throws RuntimeException When cannot auto-discover package path using CLI composer package name.
	 */
	protected function cliPackagePath(): string {
		return $this->discoveredInstalledPathOf( package: $this->packages['cli'] ?? null )
			?? throw new RuntimeException( sprintf( self::NON_DISCOVERABLE_CLI_PATH, __METHOD__ ) );
	}

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
		return $this->discoveredInstalledPathOf( package: $this->packages['main'] ?? null );
	}

	/** Gets root path of target (main) package using CLI package running as: "vendor/bin/{$scriptName}". */
	protected function relativeToVendorBinPath(): ?string {
		return ( $binPath = ( $GLOBALS['_composer_bin_dir'] ?? null ) ) && is_string( $binPath )
			? dirname( $binPath, 2 )
			: null;
	}

	/** Gets root path of target (main) package using CLI package if running as standalone composer package. */
	private function relativeToCliPackagePath( string $slash = DIRECTORY_SEPARATOR ): ?string {
		if ( ! realpath( $cliPackagePath = $this->cliPackagePath() ) ) {
			return null;
		}

		// Usually, "$cliPackagePath" is: "path/to/($rootPath)/vendor/{$vendorName}/{$packageName}".
		// We'll start discovering the root path by propagating from the "$vendorName" directory.
		$currentDir = dirname( $cliPackagePath );
		$rootPath   = null;

		do {
			// If composer.json file found in current directory, must be the root path. Else keep propagating.
			( is_file( "{$currentDir}{$slash}composer.json" ) && $rootPath = $currentDir )
				|| $currentDir = dirname( $currentDir );
		} while ( null === $rootPath && $slash !== $currentDir );

		return $rootPath;
	}

	private function discoverPackageRootPath(): string {
		return $this->relativeToVendorBinPath()
			?? $this->relativeToLocalSymlinkPath()
			?? $this->relativeToCliPackagePath()
			?? throw new RuntimeException( self::INVALID_COMPOSER_PACKAGE );
	}

	/**
	 * @return array{0:string,1:?ConfigArray,2:ConfigArray} Cli path, Cli config, main config.
	 * @throws RuntimeException When package "config.php" file not found.
	 */
	private function configure( string $slash = DIRECTORY_SEPARATOR ): array {
		require_once "{$this->rootPath}{$slash}vendor{$slash}autoload.php";

		$cliPath   = $this->cliPackagePath();
		$cliConfig = is_readable( $path = "{$cliPath}{$slash}config.php" ) ? require_once $path : null;

		( is_readable( $path = "{$this->rootPath}{$slash}config.php" ) ) && $mainConfig = require_once $path;

		$config = $mainConfig ?? $cliConfig ?? throw new RuntimeException( self::NON_DISCOVERABLE_CONFIG_PATH );

		$config['commandLoader'] ??= CommandLoader::start();

		return [ $cliPath, $cliConfig, $config ];
	}

	private function discoveredInstalledPathOf( ?string $package ): ?string {
		return $this->discoverable && $package ? InstalledVersions::getInstallPath( $package ) : null;
	}
}
