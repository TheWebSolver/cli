<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

/** @template TParamNames of string */
trait ConstructorAware {
	/** @var array<TParamNames> */
	private array $paramNames;

	/**
	 * @param array<TParamNames,mixed> $updates
	 * @return ($withParamNames is true ? array<TParamNames,mixed> : mixed[])
	 */
	private function mapConstructor( array $updates = [], bool $withParamNames = false ): array {
		$map = array_map( fn( string $name ) => $updates[ $name ] ?? $this->{$name}, $this->paramNames );

		return $withParamNames ? array_combine( $this->paramNames, $map ) : $map;
	}

	/** @param array<TParamNames,mixed> $values */
	private function selfFrom( array $values ): self {
		return new self( ...$this->mapConstructor( $values ) );
	}
}
