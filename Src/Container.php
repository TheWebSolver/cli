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
	/** @var array<class-string,class-string|callable(self,array<string,mixed>):mixed> */
	private array $bindings;
	/** @var array<class-string,class-string|callable(self,array<string,mixed>):mixed> */
	private array $sharedBindings;
	/** @var array<class-string,array<class-string,class-string>> */
	private array $context;
	/** @var class-string */
	private string $contextAbstract;
	/** @var class-string */
	private string $contextId;

	/**
	 * @param class-string<T>                                            $id
	 * @param null|class-string<T>|callable(self,array<string,mixed>): T $concrete
	 * @template T of object
	 */
	public function set( string $id, string|callable|null $concrete = null ): void {
		$this->bindings[ $id ] = $concrete ?? $id;
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
	 * @param class-string<T>                                            $id
	 * @param null|class-string<T>|callable(self,array<string,mixed>): T $concrete
	 * @template T of object
	 */
	public function setShared( string $id, string|callable|null $concrete = null ): void {
		$this->sharedBindings[ $id ] = $concrete ?? $id;
	}

	/**
	 * @param class-string<T>     $id
	 * @param array<string,mixed> $args
	 * @param ?ReflectionClass<T> $reflector
	 * @return T
	 * @throws ContainerExceptionInterface&Exception When could not resolve instance from $id.
	 * @template T of object
	 */
	public function resolve( string $id, array $args, ReflectionClass $reflector = null ): mixed {
		if ( $this->isInstance( $id ) ) {
			return $this->instances[ $id ]; // @phpstan-ignore-line
		}

		$concrete = $this->bindings[ $id ] ?? $id;

		if ( is_callable( $concrete ) ) {
			return ( $instance = $concrete( $this, $args ) ) instanceof $id
				? $instance
				: $this->unresolvableEntry( $id );
		}

		$reflector ??= new ReflectionClass( $concrete );

		if ( ! $constructor = $reflector->getConstructor() ) {
			return ( $instance = $reflector->newInstance() ) instanceof $id
				? $instance
				: $this->unresolvableEntry( $id );
		}

		$instance = $reflector->newInstanceArgs(
			array( ...$args, ...$this->getContextual( $id, ...$constructor->getParameters() ) )
		);

		if ( isset( $this->sharedBindings[ $id ] ) ) {
			$this->instances[ $id ] = $instance;
		}

		return $instance instanceof $id ? $instance : $this->unresolvableEntry( $id );
	}

	public function offsetUnset( string $id ): void {
		unset( $this->bindings[ $id ] );
	}

	/** @param class-string $id */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] );
	}

	/**
	 * @param class-string<T> $id
	 * @phpstan-assert-if-true =T $this->instances[$id]
	 * @template T
	 */
	public function isInstance( string $id ): bool {
		return ( $this->instances[ $id ] ?? null ) instanceof $id;
	}

	/**
	 * @param class-string<T>     $id
	 * @param array<string,mixed> $args
	 * @return T
	 * @template T of object
	 */
	public function get( string $id, array $args = array() ): mixed {
		try {
			return $this->resolve( $id, $args );
		} catch ( Exception $e ) {
			$this->throwException( $id, $e );
		}
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
			return array();
		}

		$dependencies   = array();
		$paramTypeHints = array_reduce( $params, $this->toParamTypeByName( ... ), $dependencies );
		$resolved       = array();

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

		return array( ...$carry, ...array( $param->name => $type ) );
	}

	private function unresolvableEntry( string $id ): never {
		$unresolvedMsg = sprintf( 'Could not resolve instance from entry: "%s".', $id );

		throw new class( $unresolvedMsg ) extends Exception implements ContainerExceptionInterface {};
	}

	/** @param class-string $id */ // phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing
	private function throwException( string $id, Exception $thrown ): never {
		$notFoundMsg = sprintf( 'Unable to find entry for the given id: "%s".', $id );

		throw $this->has( $id ) || $thrown instanceof ContainerExceptionInterface
			? $thrown
			: new class( $notFoundMsg ) extends Exception implements NotFoundExceptionInterface {};
	}
}
