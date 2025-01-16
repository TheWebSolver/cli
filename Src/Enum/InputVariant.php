<?php // phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Enum;

use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Helper\InputExtractor;

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
	public function extractFrom( string $className, bool $overrideParent = false ): InputExtractor {
		return InputExtractor::when( $className )
			->needsTo( self::perform( $overrideParent ) )
			->extract( $this );
	}

	/** @param class-string<Console> $className */
	public static function extractAllFrom( string $className, bool $overrideParent = false ): InputExtractor {
		return InputExtractor::when( $className )
			->needsTo( self::perform( $overrideParent ) )
			->extract();
	}

	/** @return InputExtractor::EXTRACT_AND_* */
	private static function perform( bool $override ): int {
		return $override ? InputExtractor::EXTRACT_AND_REPLACE : InputExtractor::EXTRACT_AND_UPDATE;
	}
}
