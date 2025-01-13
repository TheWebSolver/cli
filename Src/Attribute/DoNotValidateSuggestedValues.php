<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Attribute;

use Attribute;

#[Attribute( Attribute::TARGET_CLASS )]
final readonly class DoNotValidateSuggestedValues {}
