<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Attribute;

use Attribute;
use Symfony\Component\Console\Attribute\AsCommand;

#[Attribute( ATTRIBUTE::TARGET_CLASS )]
class Command extends AsCommand {
	/** @var string[] */
	public array $altNames;
	/** The command name. It does not contain alt-names/aliases. */
	public string $commandName;

	/**
	 * @param string $namespace   The command namespace. Eg: "create" in "create:customer".
	 * @param string $name        The command name. Eg: "customer" in "create:customer".
	 * @param string $description The command description.
	 * @param bool   $isInternal  Whether the command should be shown in all available command list.
	 *                            The command works as expected. It just won't be listed.
	 * @param string ...$altNames Alternate names for the command $name. Eg: for "customer" name,
	 *                            alternate names might be "prospect", "user", etc.
	 */
	public function __construct(
		public string $namespace, // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound
		public string $name,
		public ?string $description,
		public bool $isInternal = false,
		string ...$altNames
	) {
		$this->commandName = $this->createName( $name );
		$this->altNames    = array_map( $this->createName( ... ), $altNames );

		parent::__construct( $name, $description ?: null, aliases: $altNames, hidden: $isInternal );
	}

	private function createName( string $text ): string {
		return "{$this->namespace}:{$text}";
	}
}
