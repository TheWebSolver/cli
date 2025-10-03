<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Attribute;

use Attribute;

#[Attribute( ATTRIBUTE::TARGET_CLASS )]
class Command {
	/** @var non-empty-string[] */
	public array $altNames = [];
	/** @var non-empty-string The command name. It does not contain alt-names/aliases. */
	public string $commandName;

	/**
	 * @param non-empty-string $namespace   The command namespace. Eg: "create" in "create:customer".
	 * @param non-empty-string $name        The command name. Eg: "customer" in "create:customer".
	 * @param string           $description The command description.
	 * @param bool             $isInternal  Whether the command should be shown in all available command list.
	 *                                      The command works as expected. It just won't be listed.
	 * @param non-empty-string $help        The help content of the command, displayed with the help page.
	 * @param non-empty-string ...$altNames Alternate names for the command $name. Eg: for "customer" name,
	 *                                      alternate names might be "prospect", "user", etc.
	 */
	public function __construct(
		public string $namespace,
		public string $name,
		public ?string $description,
		public ?string $help = null,
		public bool $isInternal = false,
		string ...$altNames
	) {
		$this->commandName           = $this->createName( $name );
		$altNames && $this->altNames = array_map( $this->createName( ... ), $altNames );
	}

	/** @return non-empty-string */
	private function createName( string $text ): string {
		return "{$this->namespace}:{$text}";
	}
}
