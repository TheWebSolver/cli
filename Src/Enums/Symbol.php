<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\ISO;

enum Symbol: string {
	case Tick       = '✅';
	case Cross      = '❌';
	case NotAllowed = '⛔';
	case Green      = '🟢';
	case Red        = '🔴';
	case Yellow     = '🟠';
}
