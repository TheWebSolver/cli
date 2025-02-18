<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Adapter;

use TheWebSolver\Codegarage\Cli\CommandLoader;
use TheWebSolver\Codegarage\Generator\ArrayPhpFile;

class CompilableCommandLoader extends CommandLoader {
	private ArrayPhpFile $file;

	protected function initialize(): void {
		$this->file = new ArrayPhpFile();
	}

	public function getArrayFile(): ArrayPhpFile {
		return $this->file;
	}

	protected function useFoundCommand( string $classname, callable $command, string $commandName ): void {
		$this->file->addCallable( $classname, $command ); // @phpstan-ignore-line -- $command is always callable.

		parent::useFoundCommand( $classname, $command, $commandName );
	}
}
