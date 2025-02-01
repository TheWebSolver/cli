<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Data;

use Attribute;
use TheWebSolver\Codegarage\Cli\PureArg;
use Symfony\Component\Console\Input\InputOption;

#[Attribute( Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE )]
class Flag {
	use PureArg;

	/** @var int-mask-of<InputOption::*> The input mode. */
	public readonly int $mode;
	/** @var string The short description about the flag. */
	public readonly string $desc;
	/** @var bool Whether the option can be negated. Eg: if the flag is `--show`, the negated flag'll be `--no-show`. Defaults to `false`. */
	public readonly bool $isNegatable;
	/** @var null|string|string[] The flag's shortcut. For eg: "-s" for "--show". */
	public readonly string|array|null $shortcut;

	/**
	 * @param string          $name        The option name. Eg: "show".
	 * @param string          $desc        The short description about the flag.
	 * @param bool            $isNegatable Whether the option can be negated. Eg: if the flag
	 *                                     is `--show`, the negated flag'll be `--no-show`.
	 *                                     Defaults to `false`.
	 * @param string|string[] $shortcut    Shortcut. For eg: "-s" for "--show".
	 */
	public function __construct(
		public readonly string $name,
		string $desc = null,
		bool $isNegatable = null,
		string|array $shortcut = null
	) {
		$pure = compact( 'name' );

		foreach ( array( 'desc', 'isNegatable', 'shortcut' ) as $prop ) {
			$this->collectPure( $prop, $$prop, $pure );
		}

		$this->setPure( $pure );

		$this->desc        = $desc ?? '';
		$this->shortcut    = $shortcut;
		$this->isNegatable = $isNegatable ?? false;

		$this->mode = $this->normalizeMode();
	}

	public static function from( InputOption $input ): self {
		return new self(
			name: $input->getName(),
			desc: $input->getDescription(),
			isNegatable: $input->isNegatable(),
			shortcut: $input->getShortcut(),
		);
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

	/** @return int-mask-of<InputOption::*> */
	private function normalizeMode(): int {
		$mode = InputOption::VALUE_NONE;

		$this->isNegatable && ( $mode |= InputOption::VALUE_NEGATABLE );

		return $mode;
	}
}
