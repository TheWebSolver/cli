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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use TheWebSolver\Codegarage\Cli\Enums\InputVariant;
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
	private InputAttribute $inputAttribute;

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

	final public function setInputAttribute( InputAttribute $instance ): static {
		$this->inputAttribute = $instance;

		return $this;
	}

	final public function getInputAttribute(): InputAttribute {
		return $this->inputAttribute;
	}

	/**
	 * @param Container           $container    The DI container.
	 * @param array<string,mixed> $dependencies Constructor's injected dependencies by the container.
	 * @param bool                $infer        Whether to infer inputs from this class attributes.
	 */
	final public static function start(
		Container $container = null,
		array $dependencies = array(),
		bool $infer = true
	): static {
		[$command, $reflection] = static::getInstance( $container, $dependencies );
		$command->io            = $container?->has( SymfonyStyle::class )
			? $container->get( SymfonyStyle::class )
			: new SymfonyStyle( new ArgvInput(), new ConsoleOutput() );

		$command
			// Do not override InputAttribute if already set via constructor.
			->setInputAttribute( $command->inputAttribute ?? InputAttribute::from( $reflection )->register() )
			->setApplication( $container?->get( Cli::class ) );

		return $command->isDefined() || ! $infer ? $command : $command->withDefinitionsFrom( $reflection );
	}

	/**
	 * @param ReflectionClass<static> $ref The reflection class, if any.
	 * @return string Possible return values:
	 * - **_non-empty-string:_** If class has attribute: `TheWebSolver\Codegarage\Cli\Attribute\Command`,
	 * - **_non-empty-string:_** Using classname itself: `static::CLI_NAMESPACE` . **':camelCaseClassName'**, or
	 * - **_empty-string:_**     If command name from classname is disabled: `Cli::useClassNameAsCommand(false)`.
	 */
	final public static function asCommandName( ReflectionClass $ref = null ): string {
		$ref ??= new ReflectionClass( static::class );

		if ( $attribute = Parser::parseClassAttribute( CommandAttribute::class, $ref ) ) {
			return $attribute[0]->newInstance()->commandName;
		}

		$name = str_replace( search: '_', replace: '', subject: ucwords( $ref->getShortName(), separators: '_' ) );

		return static::CLI_NAMESPACE . ':' . lcfirst( $name );
	}

	/**
	 * @param InputAttribute::INFER_AND* $mode         One of the input attribute infer modes.
	 * @param bool                       $asDefinition Whether to get Symfony input definitions or not.
	 * @return (
	 *    $asDefinition is true
	 *      ? array<class-string<Pos|Assoc|Flag>,array<string,InputArgument|InputOption>>
	 *      : array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag>>
	 * )
	 */
	final public static function inputFromAttribute(
		int $mode = InputAttribute::INFER_AND_UPDATE,
		bool $asDefinition = false,
		InputVariant ...$variant
	): array {
		$attributes = InputAttribute::from( static::class )->register( $mode, ...$variant )->parse();

		return $asDefinition ? $attributes->toSymfonyInput() : $attributes->getCollection();
	}

	final public function setDefined( bool $isDefined = true ): static {
		$this->isDefined = $isDefined;

		return $this;
	}

	final public function isDefined(): bool {
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

	/**
	 * @param array<string,mixed> $dependencies
	 *  @return array{0:static,1:ReflectionClass<static>}
	 */
	protected static function getInstance( ?Container $container, array $dependencies ): array {
		$reflection = new ReflectionClass( static::class );

		// If container provides a shared instance, use that (if not it will be converted).
		if ( $container?->isInstance( static::class ) ) {
			return array( $container->get( static::class ), $reflection );
		}

		// Clear container binding. (Hint: in CommandLoader [static::class,'start']).
		$container?->offsetUnset( static::class );
		// Only then use Container for DI. This is to prevent infinite loop.
		$command = $container?->resolve( static::class, $dependencies, true, $reflection ) ?? new static();

		if ( ! $attributes = Parser::parseClassAttribute( CommandAttribute::class, $reflection ) ) {
			$args = array( $command->setName( static::asCommandName( $reflection ) ), $reflection );
		} else {
			$attribute = $attributes[0]->newInstance();

			$command->setName( $attribute->commandName )
				->setDescription( $attribute->description ?? '' )
				->setAliases( $attribute->altNames )
				->setHidden( $attribute->isInternal );

			$args = array( $command, $reflection );
		}

		// Convert as singleton next time same command is requested to prevent recomputation.
		$container?->setInstance( static::class, $command );

		return $args;
	}

	/**
	 * Sets input definitions using Positional|Associative|Flag attributes.
	 *
	 * This method may be overridden to handle attribute extraction. Make sure
	 * to update `InputAttribute` property using `$this->setInputAttribute()`
	 * if a new instance is used to handle attribute extraction & parsing.
	 * ```php
	 * use ReflectionClass;
	 * use TheWebSolver\Codegarage\Cli\Data\Flag;
	 * use heWebSolver\Codegarage\Cli\Enums\InputVariant;
	 * use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;
	 *
	 * function withDefinitionsFrom(ReflectionClass $reflection): static {
	 *
	 *  // Updates instead of replacing whole parent attribute. Eg: for prop: "default" or "suggestedValues".
	 *  $mode = InputAttribute::INFER_AND_UPDATE;
	 *  // Optional but if only some variants needs to be parsed, provide like so:
	 *  $variants = [InputVariant::Positional, InputVariant::Associative];
	 *  $inputAttribute = InputAttribute::from($reflection)->register($mode, ...$variants)->parse();
	 *  // Other inputs before converting to input definition (in addition to class attribute).
	 *  $inputAttribute->add(new Flag('cheer', desc: 'celebrate victory!'));
	 * // Convert to symfony inputs.
	 *  $inputAttribute->toSymfonyInput($this->getDefinition());
	 *  // Ensure definitions are registered.
	 *  return $this->setInputAttribute($inputAttribute)->setDefined();
	 * }
	 * ```
	 *
	 * @param ReflectionClass<static> $reflection
	 */
	protected function withDefinitionsFrom( ReflectionClass $reflection ): static {
		$isDefined = isset( $this->inputAttribute )
			&& $this->inputAttribute->parse()->toSymfonyInput( $this->getDefinition() );

		return $this->setDefined( $isDefined );
	}
}
