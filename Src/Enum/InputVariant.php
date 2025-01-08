<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Enum;

use InvalidArgumentException;
use TheWebSolver\Codegarage\Cli\Helper\Parser;

enum InputVariant: string {
	case POSITIONAL  = 'positional';
	case ASSOCIATIVE = 'assoc';
	case SWITCHER    = 'flag';

	private const STATUS = array(
		Parser::IS_OPTIONAL => false,
		Parser::IS_VARIADIC => false,
	);

	/** @return array<string,string> */
	public static function toAssoc(): array {
		return array_column( array: self::cases(), column_key: 'value', index_key: 'name' );
	}

	/** @throws InvalidArgumentException When invalid name supplied to the current type. */
	public function validate( string $name ): string {
		return match ( $this ) {
			self::POSITIONAL => Parser::getPositionalName( input: "<{$name}>", status: self::STATUS ),
			self::SWITCHER   => Parser::getSwitchName( input: "[--{$name}]" ),
			default          => Parser::getAssociativeName(
				input: "--{$name}",
				status: array_merge(
					self::STATUS,
					array(
						Parser::IS_VALUE_OPTIONAL => str_ends_with(
							haystack: explode( separator: '=', string: $name, limit: 2 )[0],
							needle: '['
						),
					)
				)
			)
		};
	}
}
