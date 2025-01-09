<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use ReflectionClass;
use InvalidArgumentException;
use TheWebSolver\Codegarage\Cli\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use Symfony\Component\Console\Style\SymfonyStyle;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use TheWebSolver\Codegarage\Cli\Helper\CommandArgs;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Cli\Attribute\Command as CommandAttribute;

/** @phpstan-consistent-constructor */
class Console extends Command {
	protected InputInterface $input;
	protected bool $printProgress;
	private SymfonyStyle $io;

	/** @var string */
	public const CLI_NAMESPACE           = 'app';
	public const COMMAND_ARGUMENTS_ERROR = '[COMMAND_ARGUMENTS_ERROR]';
	public const COMMAND_VALUE_ERROR     = '[COMMAND_VALUE_ERROR]';
	public const LONG_SEPARATOR_LINE     = '______________________________________________________________________________';
	public const LONG_SEPARATOR          = '==============================================================================';
	final public const DEFAULT_METHOD    = '__invoke';

	/**
	 * @param ?string $name
	 * @param ?string $subcommand Subcommand is the method name that runs when command is run as the
	 *                            `wp-cli` package. Must be ignored if class is invocable.
	 * @throws InvalidArgumentException When command parsing fails.
	 */
	public function __construct( ?string $name = null, public readonly ?string $subcommand = null ) {
		$this->io = new SymfonyStyle( new ArgvInput(), new ConsoleOutput() );

		parent::__construct( $name ?? static::asCommandName() ?: null );
		$this->setCliApp();
	}

	protected function setCliApp(): void {
		$this->setApplication( Cli::app() );
	}

	final public static function start(): static {
		return ( ! $attribute = self::getCommandAttribute() )
			? new static()
			: ( new static( $attribute->commandName ) )
				->setDescription( $attribute->description ?? '' )
				->setAliases( $attribute->altNames )
				->setHidden( $attribute->isInternal );
	}

	/**
	 * @return string Possible return values:
	 * - **_non-empty-string:_** If class has attribute: `TheWebSolver\Codegarage\Cli\Attribute\Command`,
	 * - **_non-empty-string:_** Using classname itself: `static::CLI_NAMESPACE` . **':camelCaseClassName'**, or
	 * - **_empty-string:_**     If command name from classname is disabled: `Cli::app()->useClassNameAsCommand(false)`.
	 */
	final public static function asCommandName(): string {
		$reflection = new ReflectionClass( static::class );

		if ( $attribute = self::getCommandAttribute( $reflection ) ) {
			return $attribute->commandName;
		}

		return ! Cli::app()->shouldUseClassNameAsCommand() ? '' : static::CLI_NAMESPACE . ':' . lcfirst(
			str_replace( search: '_', replace: '', subject: ucwords( $reflection->getShortName(), separators: '_' ) )
		);
	}

	/**
	 * @template HelperClass of HelperInterface
	 * @phpstan-param class-string<HelperClass> $helperClass
	 * @phpstan-return HelperClass
	 */
	public function assistFrom( string $helperClass ): HelperInterface {
		return ( $helper = $this->getHelperSet()?->get( name: $helperClass ) ) instanceof $helperClass
			? $helper
			: new $helperClass();
	}

	public function io(): SymfonyStyle {
		return $this->io;
	}

	protected function initialize( InputInterface $input, OutputInterface $output ) {
		$this->io    = new SymfonyStyle( $input, $output );
		$this->input = $input;

		try {
			$this->printProgress = true === $input->getOption( name: 'progress' );
		} catch ( InvalidArgumentException ) {
			$this->printProgress = false;
		}
	}

	protected function registerArgument( Positional $command ): void {
		$this->addArgument(
			description: $command->desc,
			default: $command->default,
			name: $command->name,
			mode: $command->mode
		);
	}

	protected function registerOption( Associative $command ): void {
		$this->addOption(
			$command->name,
			$command->shortcut,
			$command->mode,
			$command->desc,
			$command->default,
			$command->options
		);
	}

	/**
	 * @param array<string,Positional> $commands The commands.
	 * @return array<int,string>
	 */
	public static function convertForResponse( array $commands ): array {
		return array_values(
			array: array_map(
				callback: static fn( Positional $c ): string => $c->name . ' => ' . $c->desc,
				array: $commands
			)
		);
	}

	/** @param array<string,Positional> $commands */
	protected function validatePositionalIfMoreThanOne( array $commands, string $type ): ?Positional {
		if ( count( value: $commands ) <= 1 ) {
			return array_shift( array: $commands );
		}

		$this->io->error(
			message: array(
				static::COMMAND_ARGUMENTS_ERROR,
				"Positional $type Arg cannot be registered more than \"1\".",
				static::LONG_SEPARATOR,
				"Registered Positional $type Args are:",
				...static::convertForResponse( $commands ),
				static::LONG_SEPARATOR,
				'SOLUTION:',
				static::LONG_SEPARATOR_LINE,
				"Keep only one Positional $type Arg and convert others to Optional Associative Args.",
			)
		);

		exit;
	}

	/** @param ?ReflectionClass<static> $reflection */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	private static function getCommandAttribute( ?ReflectionClass $reflection = null ): ?CommandAttribute {
		$reflection ??= new ReflectionClass( static::class );

		return ( $attribute = ( $reflection->getAttributes( CommandAttribute::class )[0] ?? false ) )
			? $attribute->newInstance()
			: null;
	}
}
