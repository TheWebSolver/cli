<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use ReflectionClass;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Container\Container;
use TheWebSolver\Codegarage\Cli\Container as DropInContainer;

trait ContainerAware {
	final protected const FIRST_PARTY_CONTAINER_CLASS = '\\TheWebSolver\\Codegarage\\Container\\Container';

	/** @param array{0:?ContainerInterface,1?:ReflectionClass<static>,2?:array<string,mixed>} $options */
	private static function resolveSharedFromContainer( array $options ): ?static {
		if ( is_null( $options[0] ) ) {
			return null;
		}

		$firstPartyContainer = self::FIRST_PARTY_CONTAINER_CLASS;

		return $options[0] instanceof DropInContainer || is_a( $options[0], $firstPartyContainer )
			? self::withFirstPartyContainer( $options, shared: true )
			: null;
	}

	/** @param array{0:Container|DropInContainer,1?:ReflectionClass<static>,2?:array<string,mixed>} $options */
	private static function withFirstPartyContainer( array $options, bool $shared = false ): static {
		$container = $options[0];

		if ( $container->isInstance( static::class ) ) {
			return $container->get( static::class );
		}

		if ( $shared ) {
			$container->offsetUnset( static::class );
		}

		$reflection = $options[1] ?? null;
		$args       = $options[2] ?? array();
		$instance   = $reflection
			? $container->resolve( static::class, $args, reflector: $reflection )
			: $container->get( static::class, $args );

		return $shared ? $container->setInstance( static::class, $instance ) : $instance;
	}
}
