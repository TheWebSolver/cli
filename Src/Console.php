<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use ReflectionClass;
use InvalidArgumentException;
use TheWebSolver\Codegarage\Cli\Cli;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use Symfony\Component\Console\Input\InputOption;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Container\Container;
use Symfony\Component\Console\Style\SymfonyStyle;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Cli\Attribute\Command as CommandAttribute;

/** @phpstan-consistent-constructor */
class Console extends Command {
	protected bool $printProgress;
	private SymfonyStyle $io;
	private bool $isDefined = false;

	/** @var string */
	public const CLI_NAMESPACE           = 'app';
	public const COMMAND_ARGUMENTS_ERROR = '[COMMAND_ARGUMENTS_ERROR]';
	public const COMMAND_VALUE_ERROR     = '[COMMAND_VALUE_ERROR]';
	public const LONG_SEPARATOR_LINE     = '______________________________________________________________________________';
	public const LONG_SEPARATOR          = '==============================================================================';

	// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
	public function __construct( ?string $name = null ) {
		parent::__construct( $name );
	}

	final public static function start( Container $container = null ): static {
		[ $command, $ref ] = static::getInstance( $container );
		$command->io       = $container?->get( SymfonyStyle::class )
			?? new SymfonyStyle( new ArgvInput(), new ConsoleOutput() );

		$command->setApplication( $container?->get( Cli::class ) );

		return $command->isDefined() ? $command : $command->withDefinitionsFromAttribute( $ref )->setDefined();
	}

	/**
	 * @param Container               $container The container instance.
	 * @param ReflectionClass<static> $ref       The reflection class, if any.
	 * @return string Possible return values:
	 * - **_non-empty-string:_** If class has attribute: `TheWebSolver\Codegarage\Cli\Attribute\Command`,
	 * - **_non-empty-string:_** Using classname itself: `static::CLI_NAMESPACE` . **':camelCaseClassName'**, or
	 * - **_empty-string:_**     If command name from classname is disabled: `Cli::app()->useClassNameAsCommand(false)`.
	 */
	final public static function asCommandName( // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
		Container $container = null,
		ReflectionClass $ref = null
	): string {
		$ref ??= new ReflectionClass( static::class );

		if ( $attribute = Parser::parseClassAttribute( CommandAttribute::class, $ref ) ) {
			return $attribute[0]->newInstance()->commandName;
		}

		if ( ! $container?->get( Cli::class )->shouldUseClassNameAsCommand() ) {
			return '';
		}

		$name = str_replace( search: '_', replace: '', subject: ucwords( $ref->getShortName(), separators: '_' ) );

		return static::CLI_NAMESPACE . ':' . lcfirst( $name );
	}

	/** @return array{0:static,1:ReflectionClass<static>} */
	protected static function getInstance( ?Container $container ): array {
		// Clear CommandLoader binding with $command::start() to prevent infinite loop.
		$container?->offsetUnset( static::class );

		$ref = new ReflectionClass( static::class );
		$cli = $container?->resolve( static::class, array(), true, $ref ) ?? new static();

		if ( ! $attributes = Parser::parseClassAttribute( CommandAttribute::class, $ref ) ) {
			$args = array( $cli->setName( static::asCommandName( $container, $ref ) ), $ref );
		} else {
			$attribute = $attributes[0]->newInstance();

			$cli->setName( $attribute->commandName )
				->setDescription( $attribute->description ?? '' )
				->setAliases( $attribute->altNames )
				->setHidden( $attribute->isInternal );

			$args = array( $cli, $ref );
		}

		// Rebind with the instantiated command for Symfony to resolve it when command is ran.
		$container?->set( static::class, static fn(): static => $cli );

		return $args;
	}

	/** @return ($toInput is true ? ?InputArgument[] : ?Positional[]) */
	final public static function argumentsFromAttribute( bool $toInput = false ): ?array {
		return Parser::parseInputAttribute( Positional::class, static::class, $toInput );
	}

	/** @return ($toInput is true ? ?InputOption[] : ?Associative[]) */
	final public static function optionsFromAttribute( bool $toInput = false ): ?array {
		return Parser::parseInputAttribute( Associative::class, static::class, $toInput );
	}

	/** @return ($toInput is true ? ?InputOption[] : ?Flag[]) */
	final public static function flagsFromAttribute( bool $toInput = false ): ?array {
		return Parser::parseInputAttribute( Flag::class, static::class, $toInput );
	}

	/** @param ReflectionClass<static> $reflection */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function withDefinitionsFromAttribute( ReflectionClass $reflection ): static {
		$definition    = $this->getDefinition();
		$inputArgument = Parser::parseInputAttribute( Positional::class, $reflection, toInput: true );
		$inputOption   = Parser::parseInputAttribute( Associative::class, $reflection, toInput: true );
		$optionAsFlag  = Parser::parseInputAttribute( Flag::class, $reflection, toInput: true );

		$definition->addArguments( $inputArgument );
		$definition->addOptions( array( ...( $inputOption ?? array() ), ...( $optionAsFlag ?? array() ) ) );

		return $this;
	}

	public function setDefined( bool $isDefined = true ): static {
		$this->isDefined = $isDefined;

		return $this;
	}

	public function isDefined(): bool {
		return $this->isDefined;
	}

	/**
	 * @template HelperClass of HelperInterface
	 * @phpstan-param class-string<HelperClass> $helperClass
	 * @phpstan-return HelperClass
	 */
	public function assistFrom( string $helperClass ): HelperInterface {
		return ( $helper = $this->getHelperSet()?->get( $helperClass ) ) instanceof $helperClass
			? $helper
			: new $helperClass();
	}

	public function io(): SymfonyStyle {
		return $this->io;
	}

	protected function initialize( InputInterface $input, OutputInterface $output ) {
		$this->io = new SymfonyStyle( $input, $output );

		try {
			$this->printProgress = true === $input->getOption( 'progress' );
		} catch ( InvalidArgumentException ) {
			$this->printProgress = false;
		}
	}
}
