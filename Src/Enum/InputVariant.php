<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Enum;

enum InputVariant: string {
	case Positional  = 'argument';
	case Associative = 'option';
	case Flag        = 'flag';
}
