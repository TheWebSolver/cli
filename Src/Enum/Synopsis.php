<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Enum;

use Closure;
use ValueError;
use TheWebSolver\Codegarage\Cli\Helper\Parser;

enum Synopsis: string {
	// Top levels.
	case ShortDescription = 'shortdesc';
	case LongDescription  = 'longdesc';
	case Data             = 'synopsis';

	// Nested inside ::DATA.
	case Description = 'description';
	case Variadic    = 'repeating';
	case Optional    = 'optional';
	case Options     = 'options';
	case Default     = 'default';
	case Value       = 'value';
	case Type        = 'type';
	case Name        = 'name';

	private function getDebugTypeFrom( mixed $value ): string {
		$type   = get_debug_type( $value );
		$format = '%1$s => ["%2$s"]';

		return match ( true ) {
			is_bool( $value )     => sprintf( $format, $type, true === $value ? 'true' : 'false' ),
			is_scalar( $value )   => sprintf( $format, $type, $value ),
			is_callable( $value ) => $value instanceof Closure ? sprintf( $format, 'callable', $type ) : 'callable',
			is_object( $value )   => sprintf( $format, 'instanceof', $value::class ),
			default               => $type
		};
	}

	private function throwInvalid( string $expectedType, string $given ): never {
		throw new ValueError(
			sprintf(
				'The synopsis key: "%1$s" does not have a valid value. Expected: "%2$s", Given: "%3$s".',
				$this->value,
				$expectedType,
				$given
			)
		);
	}

	private function isNonEmptyString( mixed $value ): bool {
		return is_string( $value ) && '' !== $value;
	}

	/** @return array<bool|string> */
	private function hasValidKeyValue( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array(
				false,
				'"value" must only be a non-empty-array.',
				$this->getDebugTypeFrom( $value ),
			);
		}

		$optional = self::Optional->value;

		if ( ! array_key_exists( key: $optional, array: $value ) || true !== $value[ $optional ] ) {
			return array(
				false,
				"\"\$value['$optional']\" must only be a boolean true.",
				$this->getDebugTypeFrom( $value[ $optional ] ),
			);
		}

		$name = self::Name->value;

		if (
			array_key_exists( key: $name, array: $value )
			&& ! $this->isNonEmptyString( $value[ $name ] )
		) {
			return array(
				false,
				"\"\$value['$name']\" must be a non-empty string.",
				$this->getDebugTypeFrom( $value[ $name ] ),
			);
		}

		return array( true, '', '' );
	}

	/** @throws ValueError When invalid value type given. */
	public function validate( mixed $value ): mixed {
		return match ( $this ) {
			self::Data => $this->value,

			self::ShortDescription,
			self::LongDescription,
			self::Description,
			self::Name => is_string( $value ) && '' !== $value ? $value : $this->throwInvalid(
				expectedType: 'non-empty-string',
				given: $this->getDebugTypeFrom( $value )
			),

			self::Type => is_string( $value ) && InputVariant::tryFrom( $value )
				? $value
				: $this->throwInvalid(
					given: $this->getDebugTypeFrom( $value ),
					expectedType: "string => ['" . implode( separator: "' | '", array: Parser::parseBackedEnumValue( InputVariant::class, true ) ) . "']"
				),

			self::Variadic,
			self::Optional => is_bool( $value ) ? $value : $this->throwInvalid(
				expectedType: "boolean => ['true' | 'false']",
				given: $this->getDebugTypeFrom( $value )
			),

			self::Options => is_array( $value ) && ! empty( $value ) ? $value : $this->throwInvalid(
				expectedType: 'non-empty-array',
				given: $this->getDebugTypeFrom( $value )
			),

			self::Value => ( [ $valid, $reason, $type ] = $this->hasValidKeyValue( $value ) ) && $valid
				? $value
				: $this->throwInvalid(
					expectedType: "$reason => array{optional?:boolean(true),name?:non-empty-string}",
					given: (string) $type
				),

			default => $value,
		};//end match
	}
}
