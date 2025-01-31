<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Attribute;
use Symfony\Component\Console\Input\InputOption;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
readonly class Flag {
	/** @var int-mask-of<InputOption::*> */
	public int $mode;

	/**
	 * @param string               $name        The option name. Eg: "show".
	 * @param string               $desc        The short description about the option.
	 * @param bool                 $isNegatable Whether the option can be negated. Eg: if the flag
	 *                                          is `--show`, the negated flag'll be `--no-show`.
	 * @param null|string|string[] $shortcut Shortcut. For eg: "-s" for "--show".
	 */
	public function __construct(
		public string $name,
		public string $desc = '',
		public bool $isNegatable = false,
		public null|string|array $shortcut = null
	) {
		$this->normalizeMode();
	}

	public function __toString() {
		return $this->name;
	}

	public function __debugInfo() {
		return array(
			'name'        => $this->name,
			'desc'        => $this->desc,
			'isNegatable' => $this->isNegatable,
			'shortcut'    => $this->shortcut,
		);
	}

	/** @param array{desc?:string,isNegatable?:bool,shortcut?:null|string|array{}} $args */
	public function with( array $args ): self {
		return new self(
			name: $this->name,
			desc: $args['desc'] ?? $this->desc,
			isNegatable: $args['isNegatable'] ?? $this->isNegatable,
			shortcut: $args['shortcut'] ?? $this->shortcut
		);
	}

	public function input(): InputOption {
		return new InputOption(
			$this->name,
			$this->shortcut,
			$this->mode,
			$this->desc,
			default: null,           // Cannot have default value.
			suggestedValues: array() // Cannot suggest values.
		);
	}

	private function normalizeMode(): void {
		$mode = InputOption::VALUE_NONE;

		if ( $this->isNegatable ) {
			$mode |= InputOption::VALUE_NEGATABLE;
		}

		$this->mode = $mode;
	}
}
