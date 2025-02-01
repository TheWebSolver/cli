<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

/** Intended to be used by class that can also be used as an attribute. */
trait PureArg {
	/** @var mixed[] */
	private array $pureArgs;

	/**
	 * Gets user provided arguments that are explicitly passed. i.e. whose values cannot be `null`.
	 *
	 * @return mixed[] Empty array if already purged.
	 */
	public function getPure(): array {
		return $this->pureArgs ?? array();
	}

	/** @return bool True if not purged before, false otherwise. */
	public function purgePure(): bool {
		if ( isset( $this->pureArgs ) ) {
			unset( $this->pureArgs );

			return true;
		}

		return false;
	}

	/**
	 * Sets user provided arguments. It can only be used once.
	 *
	 * @param mixed[] $args
	 */
	private function setPure( array $args ): static {
		$this->pureArgs ??= $args;

		return $this;
	}

	/**
	 * @param mixed               $value The `null` value is never collected.
	 * @param array<string,mixed> $pure  Collection with `$arg` as key and NOT NULL `$value` as value.
	 */
	private function collectPure( string $arg, mixed $value, array &$pure ): void {
		null !== $value && ( $pure[ $arg ] = $value );
	}
}
