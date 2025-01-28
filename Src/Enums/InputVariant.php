<?php // phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Enums;

use TheWebSolver\Codegarage\Cli\Data\Flag;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;

enum InputVariant: string {
	case Positional  = 'argument';
	case Associative = 'option';
	case Flag        = 'flag';

	/** @return class-string<Positional|Associative|Flag> */
	public function getClassName(): string {
		return match ( $this ) {
			self::Positional  => Positional::class,
			self::Associative => Associative::class,
			self::Flag        => Flag::class,
		};
	}

	/** @param class-string<Positional|Associative|Flag> $className */
	public static function fromAttribute( string $className ): ?self {
		return array_filter( self::cases(), static fn( self $v ) => $v->getClassName() === $className )[0] ?? null;
	}
}
