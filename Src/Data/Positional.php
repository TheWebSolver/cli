<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

final readonly class Positional {
	public function __construct(
		public bool $isVariadic,
		public bool $isOptional,
		public string $name,
		public string $desc,
		public int $mode,
		public mixed $default = null,
	) {
	}

	/** @param array{isVariadic?:bool,isOptional?:bool,name?:string,desc?:string,mode?:int,default?:mixed} $args */
	public function recreateFrom( array $args ): self {
		return new self(
			$args['isVariadic'] ?? $this->isVariadic,
			$args['isOptional'] ?? $this->isOptional,
			$args['name'] ?? $this->name,
			$args['desc'] ?? $this->desc,
			$args['mode'] ?? $this->mode,
			$args['default'] ?? $this->default
		);
	}
}
