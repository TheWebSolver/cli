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

	final public function getInputAttribute(): ?InputAttribute {
		return $this->inputAttribute ?? null;
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
	final public static function asCommandName( ReflectionClass $ref = null ): string {
		$ref ??= new ReflectionClass( static::class );

		if ( $attribute = Parser::parseClassAttribute( CommandAttribute::class, $ref ) ) {
			return $attribute[0]->newInstance()->commandName;
		}

		$name = str_replace( search: '_', replace: '', subject: ucwords( $ref->getShortName(), separators: '_' ) );

		return static::CLI_NAMESPACE . ':' . lcfirst( $name );
	}

	/**
	 * @param InputAttribute::INFER_AND* $mode One of the input attribute infer modes.
	 * @return (
	 *    $asDefinition is true
	 *      ? array<class-string<Pos|Assoc|Flag>,array<string,InputArgument|InputOption>>
	 *      : array<class-string<Pos|Assoc|Flag>,array<string,Pos|Assoc|Flag>>
	 * )
	 */
	final public static function getInputs(
		int $mode = InputAttribute::INFER_AND_UPDATE,
		bool $asDefinition = false,
		InputVariant ...$variant
	): array {
		$attributes = InputAttribute::from( static::class )->do( $mode, ...$variant );

		return $asDefinition ? $attributes->toInput() : $attributes->getCollection();
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

	/**
	 * Sets input definitions using Positional|Associative|Flag attributes.
	 *
	 * This method may be overridden to handle attribute extraction. Make sure
	 * to update `InputAttribute` property by using respective setter method:
	 * ```php
	 * $inputAttribute = InputAttribute::from(static::class)->do(InputAttribute::INFER_AND_UPDATE);
	 * $this->setInputAttribute($inputAttribute);
	 * ```
	 *
	 * @param ReflectionClass<static> $reflection
	 */
	protected function withDefinitionsFromAttribute( ?ReflectionClass $reflection = null ): static {
		$inputAttribute = InputAttribute::from( $reflection ?? static::class )
			->do( InputAttribute::INFER_AND_UPDATE );

		$inputAttribute->toInput( $this->getDefinition() );

		return $this->setInputAttribute( $inputAttribute )->setDefined();
	}

	final protected function setInputAttribute( InputAttribute $instance ): static {
		$this->inputAttribute = $instance;

		return $this;
	}
}
