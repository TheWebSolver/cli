<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Helper;

use ValueError;
use TheWebSolver\Codegarage\Cli\Enum\Synopsis;
use TheWebSolver\Codegarage\Cli\Enum\InputVariant;

class CommandArgs {
	private ?string $currentSynopsisName = null;
	private readonly string $title;
	private readonly ?string $desc;
	/** @var array<array-key,array{type:string,name:string,description?:string,optional?:bool,repeating?:bool,default?:string,options?:array<mixed>,negatable?:bool}> */
	private array $data;

	public function __construct( string $title, ?string $desc = null ) {
		$this->title = Synopsis::ShortDescription->validate( $title );
		$this->desc  = $desc;
	}

	/**
	 * @return ?array{
	 *  shortdesc:string,
	 *  longdesc?:string,
	 *  synopsis:array<array-key,array{type:string,name:string,description?:string,optional?:bool,repeating?:bool,default?:string,options?:array<mixed>,negatable?:bool}>
	 * }
	 */
	public function toArray(): ?array {
		if ( empty( $this->data ) ) {
			return null;
		}

		$spec = array( Synopsis::ShortDescription->value => $this->title );

		if ( $this->desc ) {
			$spec[ Synopsis::LONG_DESCRIPTION->value ] = $this->desc;
		}

		$spec[ Synopsis::DATA->value ] = $this->data;

		return $spec;
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function getDesc(): ?string {
		return $this->desc;
	}

	/** @phpstan-return ?CommandSynopsis */
	public function getSpecFor( string $name ): ?array {
		return $this->data[ $name ] ?? null;
	}

	/**
	 * Gets either the passed {@param `$name`} or the Synopsis name added using
	 * {@method `CommandArgs::for()` preceding the synopsis setter method
	 * inside which this method is being called.
	 *
	 * This removes the hassle of passing Synopsis name on each method that is called immediately
	 * to set other values of the Synopsis for the same name.
	 *
	 * NOTE: The method `CommandArgs::for()` must not be called until Synopsis' all data are set.
	 *       Otherwise, the Synopsis data will be set to the wrong name. This surely
	 *       will generate an unexpected command args value.
	 */
	private function get( ?string $name ): ?string {
		return $name ?? $this->currentSynopsisName;
	}

	private static function getDashPrefixCount( string $from ): int {
		return str_starts_with( haystack: $from, needle: '--' )
			? 2
			: ( str_starts_with( haystack: $from, needle: '-' ) ? 1 : 0 );
	}

	private static function clean( string $from ): string {
		[ , $from ] = Parser::hasOptional( $from ); // "[" & "]" is stripped here.
		$prefix     = self::getDashPrefixCount( $from ); // Possible prefixes: "--" & "<".
		$suffix     = 0; // Possible suffixes: ">...".
		$positional = false;

		if ( str_starts_with( haystack: $from, needle: '<' ) ) {
			++$prefix;

			$positional = true;
		}

		if ( $positional && str_contains( haystack: $from, needle: '.' ) ) {
			$suffix = substr_count( haystack: $from, needle: '.' );
		}

		if ( $positional && str_ends_with(
			needle: '>' . ( $suffix ? str_repeat( string: '.', times: $suffix ) : '' ),
			haystack: $from
		) ) {
			++$suffix;
		}

		return substr( string: $from, offset: $prefix, length: $suffix ? - $suffix : null );
	}

	public function for( string $name, InputVariant $type ): self {
		$name = $type->validate( name: self::clean( from: Synopsis::NAME->validate( value: $name ) ) );

		$this->data[ $name ]       = array( Synopsis::NAME->value => $name );
		$this->currentSynopsisName = $name;

		$this->add( for: $name, spec: Synopsis::TYPE, value: $type->value );

		if ( InputVariant::SWITCHER === $type ) {
			$this->add( Synopsis::OPTIONAL, value: true, for: $name, overrideExisting: true );
		}

		return $this;
	}

	private function add( Synopsis $spec, mixed $value, ?string $for, bool $overrideExisting = false ): self {
		$key  = $spec->value;
		$name = $this->has( name: $this->get( name: $for ), for: $key );

		if ( $overrideExisting ) {
			unset( $this->data[ $name ][ $key ] );
		} elseif ( array_key_exists( $key, array: $this->data[ $name ] ) ) {
				return $this;
		}

		$this->data[ $name ][ $key ] = $spec->validate( $value );

		return $this;
	}

	public function addDesc( string $value, string $name = null ): self {
		return $this->add( for: $name, spec: Synopsis::DESCRIPTION, value: $value );
	}

	/** @param array<mixed> $value */
	public function addOptions( array $value, string $name = null ): self {
		return $this->add( for: $name, spec: Synopsis::OPTIONS, value: $value );
	}

	public function addOptional( bool $value, ?string $name = null ): self {
		$this->add( for: $name, spec: Synopsis::OPTIONAL, value: $value );

		if ( $value ) {
			$this->add( for: $name, spec: Synopsis::VALUE, value: array( Synopsis::OPTIONAL->value => true ) );
		}

		return $this;
	}

	public function addVariadic( bool $value, ?string $name = null ): self {
		return $this->add( for: $name, spec: Synopsis::VARIADIC, value: $value );
	}

	public function addDefault( mixed $value, ?string $name = null ): self {
		return $this->add( for: $name, spec: Synopsis::DEFAULT, value: $value );
	}

	private function has( ?string $name, string $for ): string {
		if ( is_string( value: $name ) && ( $this->data[ $name ] ?? null ) ) {
			return $name;
		}

		throw new ValueError(
			message: sprintf(
				'Invalid name: "%1$s". Use "%2$s" to add name before adding value for synopsis: "%3$s".',
				$name ?? '[name not given]',
				self::class . '::for()',
				$for
			)
		);
	}
}
