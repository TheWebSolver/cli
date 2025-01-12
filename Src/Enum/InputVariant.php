<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Enum;

use InvalidArgumentException;
use TheWebSolver\Codegarage\Cli\Helper\Parser;

enum InputVariant: string {
	case Positional  = 'argument';
	case Associative = 'option';
	case Flag        = 'flag';

	/** @throws InvalidArgumentException When invalid name supplied to the current type. */
	public function validate( string $name ): string {
		$defaultStatus = array(
			Parser::IS_OPTIONAL => false,
			Parser::IS_VARIADIC => false,
		);

		return match ( $this ) {
			self::Positional => Parser::getPositionalName( input: "<{$name}>", status: $defaultStatus ),
			self::Flag       => Parser::getSwitchName( input: "[--{$name}]" ),
			default          => Parser::getAssociativeName(
				input: "--{$name}",
				status: array_merge(
					$defaultStatus,
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
