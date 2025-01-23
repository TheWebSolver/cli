<?php // phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Enums;

use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;

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

	/** @param class-string<Console> $className */
	public function extractFrom( string $className, bool $overrideParent = false ): InputAttribute {
		return InputAttribute::from( $className )
			->do( self::perform( $overrideParent ), $this );
	}

	/** @param class-string<Console> $className */
	public static function extractAllFrom( string $className, bool $overrideParent = false ): InputAttribute {
		return InputAttribute::from( $className )
			->do( self::perform( $overrideParent ) );
	}

	/** @param class-string<Positional|Associative|Flag> $className */
	public static function fromAttribute( string $className ): ?self {
		return array_filter( self::cases(), static fn( self $v ) => $v->getClassName() === $className )[0] ?? null;
	}

	/** @return InputAttribute::INFER_AND_* */
	private static function perform( bool $override ): int {
		return $override ? InputAttribute::INFER_AND_REPLACE : InputAttribute::INFER_AND_UPDATE;
	}
}
