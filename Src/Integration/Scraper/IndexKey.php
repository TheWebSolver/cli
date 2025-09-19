<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Integration\Scraper;

use OutOfBoundsException;

final readonly class IndexKey {
	/** @placeholder %s: The index key value. */
	public const CANNOT_USE = '"%s" cannot be used as an index key.';
	/** @placeholder %s: list of allowed collectable values. */
	public const AVAILABLE_OPTION       = 'Available option: [%s]';
	public const ONLY_DISALLOWED_OPTION = 'Only one collectable value provided that is not allowed to be used as an index key.';

	/**
	 * @param string   $value      One of the values in {@param $collection}. To be used as index for collected dataset.
	 * @param string[] $collection List of collectable values that can be used as index key.
	 * @param string[] $disallowed Subset of collectable values that cannot be used as index key.
	 */
	public function __construct( public ?string $value, public array $collection, public array $disallowed = [] ) {}

	/** @throws OutOfBoundsException When index key is invalid or not allowed. */
	public function validated(): self {
		$allowed = array_diff( $this->collection, $this->disallowed );

		return match ( true ) {
			default                                                     => $this,
			empty( $allowed )                                           => $this->throwInvalid( replacements: null ),
			in_array( $this->value, $this->disallowed, strict: true ),
			! in_array( $this->value, $this->collection, strict: true ) => $this->throwInvalid( replacements: $allowed ),
		};
	}

	/**
	 * @param ?array<string|int> $replacements
	 * @throws OutOfBoundsException Based on key given.
	 */
	private function throwInvalid( ?array $replacements ): never {
		$prefix = sprintf( self::CANNOT_USE, $this->value );
		$suffix = null === $replacements
			? self::ONLY_DISALLOWED_OPTION
			: sprintf( self::AVAILABLE_OPTION, '"' . implode( '" | "', $replacements ) . '"' );

		throw new OutOfBoundsException( trim( sprintf( '%1$s %2$s', $prefix, $suffix ) ) );
	}
}
