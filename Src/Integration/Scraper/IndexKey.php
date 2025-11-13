<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Integration\Scraper;

use OutOfBoundsException;

final readonly class IndexKey {
	/** @placeholder `%s`: The index key value. */
	public const INVALID = '"%s" cannot be used as an index key.';
	/** @placeholder `%s`: list of allowed collectable values. */
	public const ALLOWED_COLLECTABLE = 'Available option: [%s]';
	/** @placeholder `%s`: index key. */
	public const EMPTY_COLLECTABLE = 'Given collection is empty.';
	/** @placeholder: `%s`: list of allowed/disallowed (both are same) values. */
	public const NON_COLLECTABLE = 'None of the values: [%s] in given collection is allowed to be used as an index key.';

	/**
	 * @param string   $value      One of the values in {@param $collection}. To be used as index for collected dataset.
	 * @param string[] $collection List of collectable values that can be used as index key.
	 * @param string[] $disallowed Subset of collectable values that cannot be used as index key.
	 */
	public function __construct( public ?string $value, public array $collection, public array $disallowed = [] ) {}

	/** @throws OutOfBoundsException When index key is invalid or not allowed. */
	public function validated(): self {
		if ( ! $this->value ) {
			return $this;
		} elseif ( ! $allowed = $this->withOnlyAllowed()->collection ) {
			$this->throwInvalid(
				...( $this->collection ? [ self::NON_COLLECTABLE, $this->collection ] : [ self::EMPTY_COLLECTABLE ] )
			);
		} elseif ( ! $this->valueIn( $this->collection ) || $this->valueIn( $this->disallowed ) ) {
			$this->throwInvalid( self::ALLOWED_COLLECTABLE, $allowed );
		}

		return $this;
	}

	public function withOnlyAllowed(): self {
		if ( ! $this->disallowed || ! $this->collection ) {
			return $this;
		}

		$allowed = array_diff( $this->collection, $this->disallowed );

		return $this->collection === $allowed ? $this : new self( $this->value, $allowed, disallowed: [] );
	}

	/** @param string[] $stack */
	private function valueIn( array $stack ): bool {
		return in_array( $this->value, $stack, strict: true );
	}

	/**
	 * @param ?non-empty-array<string> $allowed
	 * @throws OutOfBoundsException Based on key given.
	 */
	private function throwInvalid( string $suffix, ?array $allowed = null ): never {
		$prefix             = sprintf( self::INVALID, $this->value );
		$allowed && $suffix = sprintf( $suffix, TableActionBuilder::convertToString( $allowed ) );

		throw new OutOfBoundsException( trim( "{$prefix} {$suffix}" ) );
	}
}
