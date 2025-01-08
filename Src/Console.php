<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use InvalidArgumentException;
use TheWebSolver\Codegarage\Cli\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use Symfony\Component\Console\Style\SymfonyStyle;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use TheWebSolver\Codegarage\Cli\Helper\CommandArgs;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Generator\NamespacedClass;
use TheWebSolver\Codegarage\Cli\Helper\Parser as CommandParser;

/** @phpstan-consistent-constructor */
abstract class Console extends Command {
	public readonly NamespacedClass $builder;
	public readonly CommandParser $parser;
	protected InputInterface $input;
	protected bool $printProgress;
	private SymfonyStyle $io;

	public const COMMAND_ARGUMENTS_ERROR = '[COMMAND_ARGUMENTS_ERROR]';
	public const COMMAND_VALUE_ERROR     = '[COMMAND_VALUE_ERROR]';
	final public const CLI_NAMESPACE     = 'tws';
	public const LONG_SEPARATOR_LINE     = '______________________________________________________________________________';
	public const LONG_SEPARATOR          = '==============================================================================';
	final public const DEFAULT_METHOD    = '__invoke';
	final public const TEXT_DOMAIN       = 'tws-codegarage';

	/**
	 * @param ?string $name
	 * @param ?string $subcommand Subcommand is the method name that runs when command is run as the
	 *                            `wp-cli` package. Must be ignored if class is invocable.
	 * @throws InvalidArgumentException When command parsing fails.
	 */
	public function __construct( ?string $name = null, public readonly ?string $subcommand = null ) {
		$this->io      = new SymfonyStyle( new ArgvInput(), new ConsoleOutput() );
		$this->builder = $this->getBuilder();
		$this->parser  = ! empty( $commandArgs = $this->getCommandArgs()->toArray() )
			? CommandParser::parseFromArgs( $commandArgs )
			: CommandParser::parseFromDocBlock( $this, $this->subcommand ?? self::DEFAULT_METHOD );

		parent::__construct( $name ?? static::asCommandName() );
		$this->setApplication( Cli::app() );
	}

	final public static function instantiate(): static {
		return new static();
	}

	abstract protected function getBuilder(): NamespacedClass;

	/**
	 * Each child class should follow the WP-CLI command cookbook Third Parameter guideline
	 * to define arguments, options, & flags. This passed param value will be
	 * used to parse the arguments, options & flags.
	 *
	 * @link https://make.wordpress.org/cli/handbook/guides/commands-cookbook/#wp_cliadd_commands-third-args-parameter
	 */
	abstract public function getCommandArgs(): CommandArgs;

	/**
	 * Each child class should follow the WP-CLI command cookbook PHPDoc guideline
	 * to define arguments, options, & flags. This DocBlock will then be
	 * used to parse the arguments, options & flags.
	 *
	 * ### Recommended to use {@see @method `Console::getCommandArgs()`} to define command args.
	 *
	 * The {`@param`s} of this method are never used anywhere in the app.
	 * These are here just for completeness.
	 *
	 * @param array<int,string>    $args The   Required args.
	 * @param array<string,string> $assoc_args The optional arguments or flags.
	 * @link https://make.wordpress.org/cli/handbook/guides/commands-cookbook/#longdesc
	 * @uses Console::invokeWpCLI() Must be called inside this method.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$this->invokeWpCLI();
	}

	final public static function asCommandName(): string {
		return self::CLI_NAMESPACE . ':' . strtolower(
			string: str_replace(
				search: '_',
				replace: '',
				subject: (string) NamespacedClass::resolveClassNameFrom( fqcn: static::class )
			)
		);
	}

	protected function invokeWpCLI(): void {
		$this->getApplication()?->run();
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

	protected function addRequiredArgs(): void {
		foreach ( $this->parser->required as $command ) {
			$this->registerArgument( $command );
		}
	}

	protected function addRequiredVariadicArgs(): void {
		if ( empty( $commands = $this->parser->requiredVariadic ) ) {
			return;
		}

		if (
			( $command = $this->validatePositionalIfMoreThanOne( $commands, type: 'Required Variadic' ) )
				&& empty( $this->parser->optionalVariadic )
				&& empty( $this->parser->optional )
		) {
			$this->registerArgument( $command );

			return;
		}

		$invalid = array();

		if ( ! empty( $optionalCommands = $this->parser->optional ) ) {
			$invalid = static::convertForResponse( commands: $optionalCommands );
		}

		if ( ! empty( $variadicCommands = $this->parser->optionalVariadic ) ) {
			$invalid = array( ...$invalid, ...static::convertForResponse( commands: $variadicCommands ) );
		}

		$this->io->error(
			message: array(
				static::COMMAND_ARGUMENTS_ERROR,
				'Positional Required Variadic Arg cannot be used with other Positional Optional Args.',
				static::LONG_SEPARATOR,
				'Registered Positional Required Variadic Arg is:',
				...static::convertForResponse( commands: $this->parser->requiredVariadic ),
				static::LONG_SEPARATOR,
				'SOLUTION:',
				static::LONG_SEPARATOR_LINE,
				'Convert below Positional Optional Args to Optional Associative Args.',
				static::LONG_SEPARATOR,
				...$invalid,
			)
		);

		exit;
	}

	protected function addOptionalArg(): void {
		if ( empty( $commands = $this->parser->optional ) ) {
			return;
		}

		$invalid = array();

		if (
				( $command = $this->validatePositionalIfMoreThanOne( $commands, type: 'Optional' ) )
					&& empty( $invalid = $this->parser->optionalVariadic )
			) {
			$this->registerArgument( $command );

			return;
		}

		$this->io->error(
			message: array(
				static::COMMAND_ARGUMENTS_ERROR,
				'Positional Optional Arg cannot be used with Positional Optional Variadic Arg.',
				static::LONG_SEPARATOR,
				'Registered Positional Optional Arg is:',
				...static::convertForResponse( $commands ),
				static::LONG_SEPARATOR,
				'SOLUTION:',
				static::LONG_SEPARATOR_LINE,
				'Convert below Positional Optional Variadic Arg to Optional Associative Arg.',
				static::LONG_SEPARATOR,
				...static::convertForResponse( commands: $invalid ),
			)
		);

		exit;
	}

	protected function addOptionalVariadicArg(): void {
		if ( empty( $commands = $this->parser->optionalVariadic ) ) {
			return;
		}

		$invalid = array();

		if (
			( $command = $this->validatePositionalIfMoreThanOne( $commands, type: 'Optional Variadic' ) )
				&& empty( $invalid = $this->parser->optional )
		) {
			$this->registerArgument( $command );

			return;
		}

		$this->io->error(
			message: array(
				static::COMMAND_ARGUMENTS_ERROR,
				'Positional Optional Arg and Positional Optional Variadic Arg cannot be used on same command.',
				static::LONG_SEPARATOR,
				'Recommended to remove Positional Optional Arg and use as Optional Associative Arg.',
				static::LONG_SEPARATOR,
				...static::convertForResponse( commands: $invalid ),
				...static::convertForResponse( $commands ),
			)
		);

		exit;
	}

	protected function configure(): void {
		$this->setDescription( description: $this->parser->title );

		// WP CLI does not register method of a class that is invocable as a subcommand.
		// We'll silently ignore it and do not register for the Symfony console.
		// Unless a different method name is passed.
		if ( $this->subcommand && self::DEFAULT_METHOD !== $this->subcommand ) {
			$this->addArgument( name: $this->subcommand, mode: InputArgument::REQUIRED );
		}

		$this->addRequiredArgs();
		$this->addRequiredVariadicArgs();
		$this->addOptionalArg();
		$this->addOptionalVariadicArg();

		foreach ( $this->parser->associative as $command ) {
			$this->registerOption( $command );
		}

		foreach ( $this->parser->flag as $command ) {
			$this->registerOption( $command );
		}
	}
}
