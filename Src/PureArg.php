<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

/** Intended to be used by class that can also be used as an attribute. */
trait PureArg {
	/** @var mixed[] */
	private array $pureArgs;

	/**
	 * Gets user provided arguments that mimics `$attribute->getArguments()` feature.
	 *
	 * @return mixed[] Empty array if already purged.
	 */
	public function getPure(): array {
		return $this->pureArgs ?? array();
	}

	/**
	 * Sets user provided arguments: `func_get_args()`. It can only be used once.
	 *
	 * @param mixed[] $args
	 */
	private function setPure( array $args ): static {
		$this->pureArgs ??= $args;

		return $this;
	}

	/** @return bool True if not purged before, false otherwise. */
	public function purgePure(): bool {
		if ( isset( $this->pureArgs ) ) {
			unset( $this->pureArgs );

			return true;
		}

		return false;
	}
}
