<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Attribute;
use Symfony\Component\Console\Input\InputOption;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Flag extends Associative {
	/**
	 * @param string $name        The option name. Eg: "show".
	 * @param string $desc        The short description about the option.
	 * @param bool   $isNegatable Whether the option can be negated. Eg: if the flag
	 *                            is `--show`, the negated flag'll be `--no-show`.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Int mask for mode OK.
	public function __construct( public string $name, public string $desc, public bool $isNegatable ) {
		parent::__construct( $name, $desc );
	}

	protected function normalizeMode(): void {
		$this->mode = InputOption::VALUE_NONE;

		if ( $this->isNegatable ) {
			$this->mode |= InputOption::VALUE_NEGATABLE;
		}
	}

	/** @param array{}|callable|string $value */
	protected function normalizeSuggestion( array|callable|string $value ): void {
		$this->suggestedValues = array();
	}
}
