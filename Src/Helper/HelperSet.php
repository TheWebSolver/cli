<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use TheWebSolver\Codegarage\Cli\DirectoryScanner;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\HelperSet as SymfonyHelperSet;

class HelperSet extends SymfonyHelperSet {
	use DirectoryScanner;

	public static function register(): self {
		return ( new self() )->scan( dirname: 'Helper' );
	}

	protected function isIgnored( string $filename ): bool {
		return ! str_ends_with( $filename, $this->dirname );
	}

	protected function executeFor( string $filename, string $classname ): void {
		/** @var class-string<HelperInterface> $classname */
		$this->set( new $classname(), alias: $filename );
	}
}
