<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Symfony\Component\Console\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Cli extends Application {
	final public const ROOT           = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
	final public const NAMESPACE      = __NAMESPACE__;
	final public const GLOBAL_OPTIONS = self::ROOT . 'globals.php';

	private EventDispatcher $eventDispatcher;

	final public function __construct() {
		parent::__construct( 'Cli Application' );

		$this->setAutoExit( false );
		$this->setDispatcher( $this->eventDispatcher = new EventDispatcher() );
	}

	public function eventDispatcher(): EventDispatcher {
		return $this->eventDispatcher;
	}
}
