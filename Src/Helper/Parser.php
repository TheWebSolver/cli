<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use Throwable;
use ReflectionMethod;
use InvalidArgumentException;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Enum\Synopsis;
use Symfony\Component\Console\Input\InputOption;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Generator\DocParser;
use TheWebSolver\Codegarage\Generator\Enum\Type;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Input\InputArgument;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use TheWebSolver\Codegarage\Cli\Data\Flag;

class Parser {
	public const IS_VALUE_OPTIONAL = 'valueOptional';
	public const ASSOC_VARIADIC    = '[--<field>=<value>]';
	public const IS_ASSOCIATIVE    = 'associative';
	public const IS_POSITIONAL     = 'positional';
	public const IS_VARIADIC       = 'variadic';
	public const IS_OPTIONAL       = 'optional';
	public const IS_WRONG          = 'isWrong';
	public const VALID_TOKEN       = 'validToken';
	public const INPUT_WHEN        = 'when';

	public const ASSOCIATIVE_VALUE_PATTERN = '/^=<([a-zA-Z-_|,0-9]+)>$/';
	public const ASSOCIATIVE_KEY_PATTERN   = '/^--(?:\\[no-\\])?([a-z-_0-9]+)/';
	public const POSITIONAL_PATTERN        = '<([a-zA-Z-_|,0-9]+)>';

	/**
	 * @param array<string,Positional>  $requiredVariadic The required variadic arg.
	 * @param array<string,Positional>  $optionalVariadic The optional variadic arg.
	 * @param array<string,Associative> $associative      The option arg.
	 * @param array<string,Positional>  $required         The required arg.
	 * @param array<string,Positional>  $optional         The optional arg.
	 * @param array<string,Associative> $flag             The flag arg.
	 * @param string                    $title            The console description.
	 */
	private function __construct(
		public readonly array $requiredVariadic,
		public readonly array $optionalVariadic,
		public readonly array $associative,
		public readonly array $required,
		public readonly array $optional,
		public readonly array $flag,
		public readonly string $title
	) {}

	/** @throws InvalidArgumentException When invalid token given. */
	public static function parseFromDocBlock( string|object $source, string $methodName = '__invoke' ): self {
		$parsedNodes = is_string( value: $source )
			? DocParser::fromDocBlock( content: $source )
			: DocParser::fromMethod( new ReflectionMethod( objectOrMethod: $source, method: $methodName ) );

		$title = '';
		$flag  = $optionalVariadic = $requiredVariadic = $optional = $required = $associative = array();

		foreach ( $parsedNodes->children as $key => $node ) {
			if ( ! $node instanceof PhpDocTextNode || empty( $node->text ) ) {
				continue;
			}

			$tokenParts = self::asTokenParts( from: $node->text );

			if (
				array_key_first( $parsedNodes->children ) === $key
					&& 1 === count( $tokenParts )
					&& $title = ( str_ends_with( needle: '.', haystack: $node->text ) ? $node->text : $title )
			) {
				continue;
			}

			$maybeInput = $tokenParts[0] ?? '';
			$maybeDesc  = $tokenParts[1] ?? '';

			if ( ! self::isInput( token: $maybeInput ) ) {
				if ( self::isSwitch( name: $maybeInput ) ) {
					$name          = self::getSwitchName( $maybeInput );
					$flag[ $name ] = new Associative( name: $name, desc: $maybeDesc );

					continue;
				}

				// Insert phpDoc examples here.
				continue;
			}

			$hasPositionalTypes = self::hasPositionalTypes( ofInput: $maybeInput );

			if ( $hasPositionalTypes[ self::IS_POSITIONAL ] ) {
				self::validateInput( from: $tokenParts );

				[
					self::IS_OPTIONAL => $isOptional,
					self::IS_VARIADIC => $isVariadic
				]           = $hasPositionalTypes;
				$name       = self::getPositionalName( $maybeInput, $hasPositionalTypes );
				$positional = new Positional(
					isVariadic: $isVariadic,
					isOptional: $isOptional,
					name: $name,
					desc: self::toDescription( from: $maybeDesc ),
				);

				if ( $isOptional && $isVariadic ) {
					$optionalVariadic[ $name ] = $positional;
				} elseif ( ! $isOptional && $isVariadic ) {
					$requiredVariadic[ $name ] = $positional;
				} elseif ( ! $isVariadic && $isOptional ) {
					$optional[ $name ] = $positional;
				} else {
					$required[ $name ] = $positional;
				}

				continue;
			}//end if

			$hasAssociativeTypes = self::hasAssociativeTypes( ofInput: $maybeInput );

			if ( $hasAssociativeTypes[ self::IS_ASSOCIATIVE ] ) {
				self::validateInput( from: $tokenParts );

				[
					self::IS_VARIADIC       => $isVariadic,
					self::IS_OPTIONAL       => $isOptional,

					// Based on WP-CLI. Practically works same as $isOptional. WP-CLI use it as souped version
					// of a flag that optionally accepts a value. It may never fit in our CLI design.
					// It is available for the sake of completeness and forward compatibility.
					self::IS_VALUE_OPTIONAL => $valueOptional
				]            = $hasAssociativeTypes;
				$description = self::toDescription( from: $maybeDesc );

				if ( $isVariadic ) {
					// REVIEW: implement associative name for variadic input passing "name" in docBlock.
					$associative['field'] = new Associative(
						isVariadic: true,
						name: 'field',
						desc: $description,
					);
				} else {
					[ $default,, $options ] = self::asAssociativeInput( $tokenParts );
					$inputName              = self::getAssociativeName( $maybeInput, $hasAssociativeTypes );

					if ( $isOptional ) {
						$inputMode = InputOption::VALUE_OPTIONAL;

						/**
						 * Input not passed => false. Input passed without option => null.
						 *
						 * @link https://symfony.com/doc/current/console/input.html#options-with-optional-arguments
						 */
						$default = null === $default ? false : $default;
					} else {
						$inputMode = InputOption::VALUE_REQUIRED;
					}

					$associative[ $inputName ] = new Associative(
						suggestedValues: $options ?: array(),
						desc: $description,
						name: $inputName,
						// mode: $inputMode, /* FIXME */
						default: $default,
					);
				}//end if

				continue;
			}//end if
		}//end foreach

		return new self( $requiredVariadic, $optionalVariadic, $associative, $required, $optional, $flag, $title );
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName
	/**
	 * @param array{
	 *  shortdesc:string,
	 *  longdesc?:string,
	 *  synopsis:array<array-key,array{type:string,name:string,description?:string,optional?:bool,repeating?:bool,default?:string,options?:array<mixed>,negatable?:bool}>
	 * } $args
	 * @throws InvalidArgumentException When invalid input passed.
	 */
	// phpcs:enable
	public static function parseFromArgs( array $args ): self {
		$title = $args[ Synopsis::ShortDescription->value ];
		$flag  = $optionalVariadic = $requiredVariadic = $optional = $required = $associative = array();

		foreach ( $args[ Synopsis::Data->value ] as $command ) {
			$name       = $command[ Synopsis::Name->value ];
			$type       = $command[ Synopsis::Type->value ];
			$desc       = $command[ Synopsis::Description->value ] ?? '';
			$default    = $command[ Synopsis::Default->value ] ?? null;
			$isOptional = $command[ Synopsis::Optional->value ] ?? true;
			$isVariadic = $command[ Synopsis::Variadic->value ] ?? false;

			if ( self::IS_POSITIONAL === $type ) {
				$args = new Positional(
					isVariadic: $isVariadic,
					isOptional: $isOptional,
					name: $name,
					desc: $desc,
					default: $default
				);

				$required[ $name ] = $args;

				continue;
			}//end if

			if ( 'assoc' === $type ) {
				$associative[ $name ] = new Associative(
					suggestedValues: $command['options'] ?? array(),
					default: $default,
					desc: $desc,
					name: $name,
					// mode: $isOptional ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_REQUIRED /* FIXME */
				);
			}

			if ( 'flag' === $type ) {
				if ( true !== $isOptional ) {
					self::throwInvalidArg( name: $name, type: 'flag' );
				}

				$isNegatable = ( $command['negatable'] ?? false ) && true === $command['negatable'];

				$flag[ $name ] = new Flag( $name, $desc, $isNegatable );
			}
		}//end foreach

		return new self( $requiredVariadic, $optionalVariadic, $associative, $required, $optional, $flag, $title );
	}

	/**
	 * @param string       $input
	 * @param ?array<bool> $status
	 * @throws InvalidArgumentException When input token is invalid.
	 */
	public static function getPositionalName( string $input, ?array $status = null ): string {
		$status ??= self::hasPositionalTypes( ofInput: $input );
		$matched = self::asPositionalInputName( $input );
		$prefix  = $status[ self::IS_OPTIONAL ] ? /* [< */ 2 : /* < */ 1;
		$suffix  = $status[ self::IS_VARIADIC ] ? /* ... */ 3 : 0;
		$suffix  = $suffix + ( $status[ self::IS_OPTIONAL ] /* >] */ ? 2 : /* > */ 1 );
		$given   = substr( string: $input, offset: $prefix, length: - $suffix );

		return self::hasEqualLength( $given, $matched )
			? $matched
			: throw new InvalidArgumentException( sprintf( 'Invalid positional input name: "%s".', $input ) );
	}

	/**
	 * @param string             $input
	 * @param array<string,bool> $status
	 * @throws InvalidArgumentException When input token is invalid.
	 */
	public static function getAssociativeName( string $input, ?array $status = null ): string {
		$status ??= self::hasAssociativeTypes( ofInput: $input );
		$matched = self::asAssociativeInputName( $input );
		$given   = substr(
			string: explode( separator: '=', string: $input )[0],
			offset: $status[ self::IS_OPTIONAL ] ? /* [-- */ 3 : /* -- */ 2,
			length: $status[ self::IS_VALUE_OPTIONAL ] ? /* [ (exists before "=" sign) */ -1 : null
		);

		return self::hasEqualLength( $given, $matched ) ? $matched : throw new InvalidArgumentException(
			message: sprintf( 'Invalid associative input name: "%s".', $input )
		);
	}

	/** @throws InvalidArgumentException When invalid name given. */
	public static function getSwitchName( string $input ): string {
		$matched = self::asAssociativeInputName( $input );
		$given   = substr( string: $input, /* [-- */ offset: 3, /* ] */ length: -1 );

		return self::hasEqualLength( $given, $matched ) ? $matched : throw new InvalidArgumentException(
			message: sprintf( 'Invalid switch (flag) name: "%s".', $input )
		);
	}

	/** @return string[] */
	private static function asTokenParts( string $from ): array {
		return array_filter(
			array: array_map( callback: 'trim', array: explode( separator: PHP_EOL, string: $from ) )
		);
	}

	private static function asStringPart( string $token, int $offset ): string {
		return trim( string: substr( string: $token, offset: $offset ) );
	}

	private static function asPositionalInputName( string $from ): string {
		return preg_match( subject: $from, pattern: self::POSITIONAL_PATTERN, matches: $results )
			&& ! str_starts_with( haystack: $results[1], needle: '-' )
				? $results[1]
				: '';
	}

	private static function asAssociativeInputName( string $from ): string {
		[ , $name ] = self::hasOptional( $from );

		return preg_match( pattern: self::ASSOCIATIVE_KEY_PATTERN, subject: $name, matches: $results )
			? $results[1]
			: '';
	}

	/** @return string Texts after ":". */
	private static function toDescription( string $from ): string {
		return self::asStringPart( token: $from, offset: 2 );
	}

	/** @throws InvalidArgumentException When given string can't be set to given type. */
	private static function defaultFor( string $string, string $type ): string|bool|float|int|null {
		try {
			if ( $type ) {
				$type = Type::set( value: $string, type: $type );

				return null === $type || is_scalar( $type ) ? $type : null;
			}

			return in_array( $string, array( 'true', 'TRUE', 'false', 'FALSE' ), true )
				? Type::toBool( value: $string )
				: ( in_array( $string, array( 'null', 'NULL' ), true ) ? null : $string );
		} catch ( Throwable $e ) {
			throw new InvalidArgumentException( $e->getMessage() );
		}
	}

	/**
	 * @param string|string[] $tokenParts
	 * @return array{0:string|bool|float|int|null,1:?string,2:?array<array-key,string|bool|float|int|null>}
	 * @throws InvalidArgumentException When parsing fails.
	 * */
	public static function asAssociativeInput( string|array $tokenParts ): array {
		$tokens   = is_string( value: $tokenParts ) ? self::asTokenParts( $tokenParts ) : $tokenParts;
		$defaults = $types = $options = array();

		foreach ( $tokens as $token ) {
			if ( str_starts_with( haystack: $token, needle: $d = 'default: ' ) ) {
				$defaults[] = self::asStringPart( $token, offset: strlen( string: $d ) );
			} elseif ( str_starts_with( haystack: $token, needle: $t = 'type: ' ) ) {
				$types[] = self::asStringPart( $token, offset: strlen( string: $t ) );
			} elseif ( str_starts_with( haystack: $token, needle: $o = '- ' ) ) {
				$options[] = self::asStringPart( $token, offset: strlen( string: $o ) );
			}
		}

		if ( empty( $options ) && empty( $defaults ) ) {
			return array( null, null, null );
		}

		if ( $type = ( $types[0] ?? '' ) ) {
			if ( ! Type::tryFrom( $type ) ) {
				throw new InvalidArgumentException(
					message: sprintf(
						'Unsupported parsing type: "%s". To pass more flexible values for command input, provide command args as an array instead of docBlock.',
						$type
					)
				);
			}
		}

		$null     = Type::Null->value;
		$defaults = self::defaultFor( ...self::hasAssociative( $tokens, $defaults, $types ) );
		$values   = array();

		foreach ( $options as $value ) {
			// Only convert string null to null type and leave others as is if $type === null.
			// For more flexibility, pass command args as array instead of docBlock.
			$values[] = $type === $null && $value !== $null ? $value : self::defaultFor( $value, $type );
		}

		return array( $defaults, $type, $values );
	}

	/** @param string $token */
	private static function isInput( string $token ): bool {
		return str_contains( haystack: $token, needle: '<' )
			&& str_contains( haystack: $token, needle: '>' );
	}

	/**
	 * @param string[] $from Token parts.
	 * @throws InvalidArgumentException When input is not passed in docBlock standard.
	 */
	private static function validateInput( array $from ): void {
		if ( ! is_string( value: $input = array_shift( array: $from ) ) ) {
			return;
		}

		if ( ( $count = ( count( value: $from ) ) ) < 1 ) {
			self::throwLesserTokenParts( forInput: $input );
		}

		$desc = $from[0];

		if ( $desc && ! str_starts_with( needle: ': ', haystack: $desc ) ) {
			if ( 1 !== $count ) {
				array_shift( array: $from );
			}

			self::throwNoDescription(
				forInput: $input,
				possibleDesc: $desc,
				inToken: implode( separator: PHP_EOL . ' * ', array: $from )
			);
		}
	}

	private static function isSwitch( string $name ): bool {
		return self::startsEnds( starts: '[--', ends: ']', string: $name )
			&& ! str_contains( haystack: $name, needle: '>' );
	}

	private static function hasEqualLength( string $given, string $matched ): bool {
		return strlen( string: $given ) === strlen( string: $matched );
	}

	/**
	 * @return array<bool|string>
	 * @phpstan-return array{0:bool,1:string}
	 */
	public static function hasOptional( string $from ): array {
		if ( ( '[' === substr( $from, 0, 1 ) ) && ( ']' === substr( $from, -1 ) ) ) {
			return array( true, substr( $from, 1, -1 ) );
		}

		return array( false, $from );
	}

	/**
	 * @param string[] $tokens   The token parts.
	 * @param string[] $defaults The default values.
	 * @param string[] $types    The value types.
	 * @return string[]
	 * @throws InvalidArgumentException When default value not given or invalid type passed.
	 * @link https://make.wordpress.org/cli/handbook/guides/commands-cookbook/#longdesc
	 */
	private static function hasAssociative( array $tokens, array $defaults, array $types ): array {
		$noOfDefaults = count( value: $defaults );

		if (
			// The options opening tag must exist after input name "$token[0]" and description
			// "$token[1]" assuming description is a single sentence without line break.
			'---' === ( $tokens[2] ?? false )

			// The options closing tag must exist after all options are defined.
			&& '---' === array_pop( array: $tokens )

			// A single default value must exist and, if needed, a single typehint for that default value.
			// If type given more than one, we'll use the first one and silently ignore the rest.
			&& 1 === $noOfDefaults
			&& ( empty( $types ) || 1 === count( value: $types ) )
		) {
			return array( $defaults[0], $types[0] ?? '' );
		}

		if ( $noOfDefaults > 1 ) {
			$errorMsg = sprintf(
				'Optional associative cannot have more than one default value for input: "%s". "%d" defaults given.',
				$tokens[0],
				$noOfDefaults
			);
		} else {
			$errorMsg = sprintf(
				'Optional associative input does not have a default value for input: "%s".',
				$tokens[0]
			);
		}

		throw new InvalidArgumentException( message: $errorMsg );
	}

	/**
	 * @return array<string,bool>
	 * @phpstan-return array{positional:bool,optional:bool,variadic:bool}
	 */
	private static function hasPositionalTypes( string $ofInput ): array {
		$optional   = self::startsEnds( starts: '[<', ends: ']', string: $ofInput );
		$prefix     = ( $optional ? '[' : '' ) . '<';
		$suffix     = ( $optional ? ']' : '' );
		$variadic   = self::startsEnds( starts: $prefix, ends: ">...{$suffix}", string: $ofInput );
		$positional = $variadic ?
			: self::startsEnds( starts: $prefix, ends: ">{$suffix}", string: $ofInput );

		return compact( self::IS_POSITIONAL, self::IS_OPTIONAL, self::IS_VARIADIC );
	}

	/** @return array<string,bool> */
	private static function hasAssociativeTypes( string $ofInput ): array {
		[ $optional, $input ] = self::hasOptional( from: $ofInput );
		$associative          = str_starts_with( haystack: $ofInput, needle: $optional ? '[--' : '--' )
			&& str_contains( haystack: $input, needle: '=<' );

		$valueOptional = false;
		$variadic      = self::ASSOC_VARIADIC === $ofInput;
		$optional      = $optional && $associative;

		if ( ! $associative || $variadic ) {
			return compact(
				self::IS_ASSOCIATIVE,
				self::IS_OPTIONAL,
				self::IS_VARIADIC,
				self::IS_VALUE_OPTIONAL
			);
		}

		[ $name, $value ] = explode( separator: '=', string: $input );
		$hasFirstPart     = $hasLastPart = false;

		if ( str_ends_with( haystack: $name, needle: '[' ) ) {
			$hasFirstPart = true;
		}

		if ( str_ends_with( haystack: $value, needle: '>]' ) ) {
			$hasLastPart = true;
		}

		$valueOptional = $hasFirstPart && $hasLastPart;

		if ( $hasFirstPart && ! $hasLastPart ) {
			$associative = $optional = false;
		} elseif ( ! $hasFirstPart && $hasLastPart ) {
			$associative = $optional = false;
		}

		return compact(
			self::IS_ASSOCIATIVE,
			self::IS_OPTIONAL,
			self::IS_VARIADIC,
			self::IS_VALUE_OPTIONAL
		);
	}

	public static function startsEnds( string $starts, string $ends, string $string ): bool {
		return str_starts_with( haystack: $string, needle: $starts )
			&& str_ends_with( haystack: $string, needle: $ends );
	}

	/*
	|=============================================================================
	|
	| EXCEPTION METHODS
	|
	| TROWS AN EXCEPTION WHENEVER THESE METHODS ARE CALLED.
	|
	|=============================================================================
	*/

	/** @throws InvalidArgumentException When invalid arg given. */
	private static function throwInvalidArg( string $name, string $type ): never {
		throw new InvalidArgumentException(
			sprintf(
				'The %1$s: "%2$s" cannot be set as required. Remove "optional" key/value pair or set value as `true` like so: "optional=>true".',
				$type,
				$name
			)
		);
	}

	private static function throwLesserTokenParts( string $forInput ): never {
		throw new InvalidArgumentException(
			sprintf(
				'%s Command must have at least an argument/option and it\'s description for the input: "%s"',
				Console::COMMAND_ARGUMENTS_ERROR,
				$forInput
			)
		);
	}

	private static function throwNoDescription(
		string $forInput,
		string $possibleDesc,
		string $inToken
	): never {
		// phpcs:disable WordPress.Security.EscapeOutput.HeredocOutputNotEscaped
		throw new InvalidArgumentException(
			sprintf(
				<<<INVALID_DESC
				%1\$s Invalid format given for the input: "%2\$s".

				%3\$s

				Description provided in a wrong format in the token below:

				=> "%4\$s"

				%5\$s

				Try using input format in PHP DocBlock like below:

				%5\$s

				%6\$s
				INVALID_DESC,
				Console::COMMAND_ARGUMENTS_ERROR,
				$forInput,
				Console::LONG_SEPARATOR,
				$possibleDesc,
				Console::LONG_SEPARATOR_LINE,
				sprintf(
					<<<VALID_DESC
					/**
					 * $forInput
					 * : %1\$s
					 * %2\$s
					 */
					VALID_DESC,
					str_starts_with( needle: ':', haystack: $possibleDesc )
						? self::asStringPart( token: $possibleDesc, offset: 1 )
						: $possibleDesc,
					$inToken
				)
			)
		);
		// phpcs:enable
	}
}
