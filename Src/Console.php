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
use TheWebSolver\Codegarage\Container\Container;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use TheWebSolver\Codegarage\Cli\Enum\InputVariant;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;
use TheWebSolver\Codegarage\Cli\Data\Positional as Pos;
use TheWebSolver\Codegarage\Cli\Data\Associative as Assoc;
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
		$command->io       = $container?->has( SymfonyStyle::class )
			? $container->get( SymfonyStyle::class )
			: new SymfonyStyle( new ArgvInput(), new ConsoleOutput() );

		$command->setApplication( $container?->get( Cli::class ) );

		return $command->isDefined() ? $command : $command->withDefinitionsFromAttribute( $ref );
	}

	/**
	 * @param ReflectionClass<static> $ref The reflection class, if any.
	 * @return string Possible return values:
	 * - **_non-empty-string:_** If class has attribute: `TheWebSolver\Codegarage\Cli\Attribute\Command`,
	 * - **_non-empty-string:_** Using classname itself: `static::CLI_NAMESPACE` . **':camelCaseClassName'**, or
	 * - **_empty-string:_**     If command name from classname is disabled: `Cli::useClassNameAsCommand(false)`.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	final public static function asCommandName( ReflectionClass $ref = null ): string {
		$ref ??= new ReflectionClass( static::class );

		if ( $attribute = Parser::parseClassAttribute( CommandAttribute::class, $ref ) ) {
			return $attribute[0]->newInstance()->commandName;
		}

		$name = str_replace( search: '_', replace: '', subject: ucwords( $ref->getShortName(), separators: '_' ) );

		return static::CLI_NAMESPACE . ':' . lcfirst( $name );
	}

	/** @return array{0:static,1:ReflectionClass<static>} */
	protected static function getInstance( ?Container $container ): array {
		$ref = new ReflectionClass( static::class );

		// If container provides a shared instance, use that (if not it will be converted).
		if ( $container?->isInstance( static::class ) ) {
			return array( $container->get( static::class ), $ref );
		}

		// Clear container binding. (Hint: in CommandLoader [static::class => static::start()]).
		$container?->offsetUnset( static::class );
		// Only then use Container for DI. This is to prevent infinite loop.
		$command = $container?->resolve( static::class, array(), true, $ref ) ?? new static();

		if ( ! $attributes = Parser::parseClassAttribute( CommandAttribute::class, $ref ) ) {
			$args = array( $command->setName( static::asCommandName( $ref ) ), $ref );
		} else {
			$attribute = $attributes[0]->newInstance();

			$command->setName( $attribute->commandName )
				->setDescription( $attribute->description ?? '' )
				->setAliases( $attribute->altNames )
				->setHidden( $attribute->isInternal );

			$args = array( $command, $ref );
		}

		// Convert as singleton next time same command is requested to prevent recomputation.
		$container?->setInstance( static::class, $command );

		return $args;
	}

	/** @return ($toInput is true ? array<class-string<Pos>,array<string,InputArgument>> : array<class-string<Pos>,array<string,Pos>>) */
	final public static function positionalInputs( bool $replaceParent = false, bool $toInput = false ): array {
		return self::getInputs( $replaceParent, $toInput, InputVariant::Positional );
	}

	/** @return ($toInput is true ? array<class-string<Assoc>,array<string,InputOption>> : array<class-string<Assoc>,array<string,Assoc>>) */
	final public static function associativeInputs( bool $replaceParent = false, bool $toInput = false ): array {
		return self::getInputs( $replaceParent, $toInput, InputVariant::Associative );
	}

	/** @return ($toInput is true ? array<class-string<Flag>,array<string,InputOption>> : array<class-string<Flag>,array<string,Flag>>) */
	final public static function flagInputs( bool $replaceParent = false, bool $toInput = false ): array {
		return self::getInputs( $replaceParent, $toInput, InputVariant::Flag );
	}

	/** @return array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag|InputArgument|InputOption>> */
	final public static function getInputs( bool $replace = false, bool $toInput = false, InputVariant ...$variant ): array {
		$attributes = InputAttribute::from( static::class )->do(
			$replace ? InputAttribute::EXTRACT_AND_REPLACE : InputAttribute::EXTRACT_AND_UPDATE,
			...$variant
		);

		return $toInput ? $attributes->toInput() : $attributes->getCollection();
	}

	/** @param ReflectionClass<static> $reflection */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function withDefinitionsFromAttribute( ?ReflectionClass $reflection = null ): static {
		InputAttribute::from( $reflection ?? static::class )
			->do( InputAttribute::EXTRACT_AND_UPDATE )
			->toInput( $this->getDefinition() );

		return $this->setDefined();
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
