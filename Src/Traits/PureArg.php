<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use TheWebSolver\Codegarage\Cli\Helper\Parser;

/** Intended to be used by class that can also be used as an attribute. */
trait PureArg {
	/** @var array<string,mixed> */
	private array $pureArgs;

	public function hasPure(): bool {
		return ! ! ( $this->pureArgs ?? false );
	}

	/**
	 * Gets user provided values that are explicitly passed. i.e. not `null` values.
	 *
	 * @return array<string,mixed> Empty array if already purged.
	 */
	public function getPure(): array {
		return $this->pureArgs ?? array();
	}

	/**
	 * @param string|(string)[] $argKey
	 * @return array<string,mixed>
	 */
	public function getPureWith( string|array $argKey ): array {
		return ! $this->hasPure() ? array() : $this->filterPure( (array) $argKey, ignore: false );
	}

	/**
	 * @param string|(string)[] $argKey
	 * @return array<string,mixed>
	 */
	public function getPureWithout( string|array $argKey ): array {
		return ! $this->hasPure() ? array() : $this->filterPure( (array) $argKey, ignore: true );
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
	 * Sets user provided arguments. It can only be used once if not purged yet.
	 *
	 * @param array<string,mixed> $values
	 */
	private function setPure( array $values ): static {
		$this->pureArgs ??= $values;

		return $this;
	}

	/**
	 * Discovers user provided (not null) value for the given method.
	 *
	 * @param string  $methodName Usually `__FUNCTION__` constant value.
	 * @param mixed[] $values     Usually `func_get_args()` of the `$methodName`.
	 * @return string[] The parameter names of the provided method name.
	 */
	private function discoverPureFrom( string $methodName, array $values ): array {
		$paramNames = Parser::parseParamNamesOf( $this, $methodName );
		$validArgs  = Parser::combineParamNamesWithUserArgs( $paramNames, $values );

		array_walk( $validArgs, $this->walkPure( ... ) );

		return $paramNames;
	}

	/** @param mixed $value The `null` value is never collected (assumed user skipped that key). */
	private function walkPure( mixed $value, string $key ): void {
		null === $value || $this->pureArgs[ $key ] = $value;
	}

	/**
	 * @param string[] $argKeys
	 * @return array<string,mixed>
	 */
	private function filterPure( array $argKeys, bool $ignore = false ): array {
		$filter = $ignore
			? static fn( string $key ) => ! in_array( $key, $argKeys, strict: true )
			: static fn( string $key ) => in_array( $key, $argKeys, strict: true );

		return array_filter( $this->getPure(), $filter( ... ), ARRAY_FILTER_USE_KEY );
	}
}
