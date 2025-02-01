<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

/** @template TParam of string */
trait ConstructorAware {
	/** @var array<TParam> */
	private array $paramNames;

	/**
	 * @param array<TParam,mixed> $updates
	 * @return ($withParamNames is true ? array<TParam,mixed> : mixed[])
	 */
	private function mapConstructor( array $updates = array(), bool $withParamNames = false ): array {
		$map = array_map( fn( string $name ) => $updates[ $name ] ?? $this->{$name}, $this->paramNames );

		return $withParamNames ? array_combine( $this->paramNames, $map ) : $map;
	}

	/** @param array<TParam,mixed> $values */
	private function selfFrom( array $values, bool $namedArguments = false ): self {
		return new self( ...$this->mapConstructor( $values, $namedArguments ) );
	}
}
