<?php
declare ( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use DirectoryIterator;
use UnexpectedValueException;

trait DirectoryScanner {
	public const EXTENSION = 'php';

	private string $dirname;

	abstract protected function isIgnored( string $filename ): bool;

	abstract protected function executeFor( string $filename, string $classname ): void;

	private function scan( string $dirname ): self {
		$this->dirname = $dirname;

		foreach ( new DirectoryIterator( directory: __DIR__ . "/{$dirname}" ) as $fileInfo ) {
			if ( $fileInfo->isDot() || ! $fileInfo->isFile() ) {
				continue;
			}

			if ( self::EXTENSION !== $fileInfo->getExtension() ) {
				continue;
			}

			$name = $fileInfo->getBasename( suffix: '.' . self::EXTENSION );

			if ( $this->isIgnored( filename: $name ) ) {
				continue;
			}

			$this->executeFor(
				filename: $name,
				classname: $this->validateClassName( className: $this->toFQCN( filename: $name ) )
			);
		}

		return $this;
	}

	/** @throws UnexpectedValueException When file cannot resolve to classname. */
	private function validateClassName( string $className ): string {
		if ( ! class_exists( class: $className ) ) {
			throw new UnexpectedValueException(
				message: sprintf( 'The classname "%s" could not be resolved.', $className )
			);
		}

		return $className;
	}

	private function toFQCN( string $filename ): string {
		return __NAMESPACE__ . "\\{$this->dirname}\\{$filename}";
	}
}
