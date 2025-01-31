<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

/** Intended to be used with classes that can also be used as an attribute. */
trait PureArg {
	/** @var mixed[] */
	private readonly array $pureArgs;

	/** @return mixed[] Only user provided arguments that mimics `$attribute->getArguments()` behavior. */
	public function getPure(): array {
		return $this->pureArgs;
	}

	/** @param mixed[] $args User provided args from `func_get_args()`. It must only be called once. */
	private function setPure( array $args ): static {
		$this->pureArgs = $args;

		return $this;
	}
}
