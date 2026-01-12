<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use Exception;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

/**
 * Drop-in replacement of first-party container package with bare-minimum features.
 *
 * @link https://github.com/TheWebSolver/container
 */
class Container implements ContainerInterface {
	/** @var array<class-string,object> */
	private array $instances;
	/** @var class-string */
	private string $contextAbstract;
	/** @var class-string */
	private string $contextId;

	/**
	 * @param array<class-string,array<class-string,class-string>> $context The resolving classname as key
	 *        with array value of its constructor's param type-hint as key and type-hint's value as value.
	 * @param array<
	 *  class-string,array{0:class-string|callable(self,array<string,mixed>):mixed,1:bool}
	 * > $bindings The classname as key with array value of its concrete and whether to make it singleton or not.
	 */
	public function __construct(
		private array $context = [],
		private array $bindings = [],
	) {}

	/**
	 * @param class-string<T>                                            $id
	 * @param null|class-string<T>|callable(self,array<string,mixed>): T $concrete
	 * @template T of object
	 */
	public function set( string $id, string|callable|null $concrete = null ): void {
		$this->bindings[ $id ] = [ $concrete ?? $id, false ];
	}

	/**
	 * @param class-string<T>                                            $id
	 * @param null|class-string<T>|callable(self,array<string,mixed>): T $concrete
	 * @template T of object
	 */
	public function setShared( string $id, string|callable|null $concrete = null ): void {
		$this->bindings[ $id ] = [ $concrete ?? $id, true ];
	}

	/**
	 * @param class-string<T> $id
	 * @param T               $instance
	 * @return T
	 * @template T of object
	 */
	public function setInstance( string $id, object $instance ): object {
		return $this->instances[ $id ] = $instance;
	}

	/**
	 * @param class-string<T>     $id
	 * @param array<string,mixed> $args
	 * @param ?ReflectionClass<T> $reflector
	 * @return T
	 * @throws ContainerExceptionInterface&Exception When could not resolve instance from $id.
	 * @template T of object
	 */
	public function resolve( string $id, array $args, ?ReflectionClass $reflector = null ): object {
		if ( $this->isInstance( $id ) ) {
			return $this->instances[ $id ]; // @phpstan-ignore-line
		}

		[$concrete, $shared] = $this->bindings[ $id ] ?? [ $id, false ];

		if ( is_callable( $concrete ) ) {
			$resolved = $concrete( $this, $args );

			$resolved instanceof $id || $this->unresolvableEntry( $id );

			/** @disregard P1006 Expected type 'object'. Found 'class-string<object>' */
			return $shared ? $this->setInstance( $id, $resolved ) : $resolved;
		}

		$reflector ??= new ReflectionClass( $concrete );

		if ( ! $constructor = $reflector->getConstructor() ) {
			$resolved = $reflector->newInstance();

			$resolved instanceof $id || $this->unresolvableEntry( $id );

			return $shared ? $this->setInstance( $id, $resolved ) : $resolved;
		}

		$resolved = $reflector->newInstanceArgs(
			[ ...$args, ...$this->getContextual( $id, ...$constructor->getParameters() ) ]
		);

		$resolved instanceof $id || $this->unresolvableEntry( $id );

		return $shared ? $this->setInstance( $id, $resolved ) : $resolved;
	}

	public function offsetUnset( string $id ): void {
		unset( $this->bindings[ $id ] );
	}

	/** @param class-string $id */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] );
	}

	public function isInstance( string $id ): bool {
		return ( $this->instances[ $id ] ?? null ) instanceof $id;
	}

	/**
	 * @param class-string<T>     $id
	 * @param array<string,mixed> $args
	 * @return T
	 * @template T of object
	 */
	public function get( string $id, array $args = [] ): mixed {
		return $this->resolve( $id, $args );
	}

	/** @param class-string $concrete */
	public function when( string $concrete ): self {
		$this->contextId = $concrete;

		return $this;
	}

	/** @param class-string $abstract */
	public function needs( string $abstract ): self {
		$this->contextAbstract = $abstract;

		return $this;
	}

	/** @param class-string $concrete */
	public function give( string $concrete ): void {
		$this->context[ $this->contextId ][ $this->contextAbstract ] = $concrete;

		unset( $this->contextAbstract, $this->contextId );
	}

	/** @return array<string,mixed> $resolved */
	private function getContextual( string $id, ReflectionParameter ...$params ): array {
		if ( ! $contextual = ( $this->context[ $id ] ?? null ) ) {
			return [];
		}

		$dependencies   = [];
		$paramTypeHints = array_reduce( $params, $this->toParamTypeByName( ... ), $dependencies );
		$resolved       = [];

		foreach ( $paramTypeHints as $paramName => $abstract ) {
			( $concrete = ( $contextual[ $abstract ] ?? null ) )
				&& $resolved[ $paramName ] = $this->get( $concrete );
		}

		return $resolved;
	}

	/**
	 * @param array<string,string> $carry
	 * @return array<string,string>
	 */
	private function toParamTypeByName( array $carry, ReflectionParameter $param ): array {
		$type = ( $t = $param->getType() ) instanceof ReflectionNamedType ? $t->getName() : '';

		return [ ...$carry, ...[ $param->name => $type ] ];
	}

	/** @param class-string $id */ // phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing
	private function unresolvableEntry( string $id ): never {
		$this->has( $id ) && throw new class( sprintf( 'Could not resolve instance from entry: "%s".', $id ) )
			extends Exception implements ContainerExceptionInterface {};

		throw new class( sprintf( 'Unable to find entry for the given id: "%s".', $id ) )
			extends Exception implements NotFoundExceptionInterface {};
	}
}
