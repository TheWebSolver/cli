<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Cli extends Application {
	final public const ROOT      = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
	final public const NAMESPACE = __NAMESPACE__;

	private bool $shouldUseClassNameForCommand = true;
	private EventDispatcher $eventDispatcher;

	final public function __construct() {
		$this->setAutoExit( false );
		$this->setDispatcher( $this->eventDispatcher = new EventDispatcher() );
	}

	public function eventDispatcher(): EventDispatcher {
		return $this->eventDispatcher;
	}

	public function useClassNameAsCommand( bool $set = true ): void {
		$this->shouldUseClassNameForCommand = $set;
	}

	public function shouldUseClassNameAsCommand(): bool {
		return $this->shouldUseClassNameForCommand;
	}

	protected function getDefaultInputDefinition(): InputDefinition {
		$definition = parent::getDefaultInputDefinition();

		/** @var Associative[] */
		$globalOptions = require_once \dirname( __DIR__ ) . '/GlobalOptions.php';

		foreach ( $globalOptions as $option ) {
			$definition->addOption(
				new InputOption(
					$option->name,
					$option->shortcut,
					$option->mode,
					$option->desc,
					$option->default,
					$option->options
				)
			);
		}

		return $definition;
	}
}
