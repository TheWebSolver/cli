<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli;

use ReflectionClass;
use TheWebSolver\Codegarage\Cli\Cli;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use Symfony\Component\Console\Command\Command;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use TheWebSolver\Codegarage\Cli\Enums\InputVariant;
use Symfony\Component\Console\Helper\HelperInterface;
use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;
use TheWebSolver\Codegarage\Cli\Traits\ContainerAware;
use Symfony\Component\Console\Exception\LogicException;
use TheWebSolver\Codegarage\Cli\Data\Positional as Pos;
use TheWebSolver\Codegarage\Cli\Data\Associative as Assoc;
use TheWebSolver\Codegarage\Cli\Attribute\Command as CommandAttribute;

/** @phpstan-consistent-constructor */
class Console extends Command {
	use ContainerAware;

	private bool $isDefined = false;
	private InputAttribute $inputAttribute;

	/** @var string */
	public const CLI_NAMESPACE           = 'app';
	public const COMMAND_ARGUMENTS_ERROR = '[COMMAND_ARGUMENTS_ERROR]';
	public const COMMAND_VALUE_ERROR     = '[COMMAND_VALUE_ERROR]';
	public const LONG_SEPARATOR_LINE     = '______________________________________________________________________________';
	public const LONG_SEPARATOR          = '==============================================================================';

	/** @param ReflectionClass<Console> $ref */
	final public static function getCommandAttribute( ?ReflectionClass $ref = null ): ?CommandAttribute {
		$ref ??= new ReflectionClass( static::class );

		return ( Parser::parseClassAttribute( CommandAttribute::class, $ref )[0] ?? null )?->newInstance();
	}

	/**
	 * @param ContainerInterface  $container       The DI container.
	 * @param array<string,mixed> $constructorArgs Constructor's injected dependencies by the container.
	 */
	final public static function start( ?ContainerInterface $container = null, array $constructorArgs = [] ): static {
		$command = $container
			? self::resolveSharedFromContainer( [ $container, new ReflectionClass( static::class ), $constructorArgs ] )
			: new static( ...$constructorArgs );

		$command->setApplication( ( $app = $container?->get( Cli::class ) ) instanceof Cli ? $app : null );

		return $command;
	}

	/**
	 * @param ReflectionClass<static> $ref The reflection class, if any.
	 * @return non-empty-string Possible return values:
	 * - From command attribute: `TheWebSolver\Codegarage\Cli\Attribute\Command`, or
	 * - Using classname itself: `static::CLI_NAMESPACE` . **':camelCaseClassName'**
	 */
	final public static function asCommandName( ?ReflectionClass $ref = null ): string {
		$ref ??= new ReflectionClass( static::class );

		return self::getCommandAttribute( $ref )->commandName ?? self::commandNameFromClassname( $ref );
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

	public function __construct( ?string $name = null ) {
		try {
			parent::__construct( $name );
		} catch ( LogicException ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Ignore exception created by name. Filled either by attribute or from classname.
		}

		$this->registerParsedInputsToDefinitions( $this->registerCommandDetails() );
	}

	final public function setInputAttribute( InputAttribute $instance ): static {
		$this->inputAttribute = $instance;

		return $this;
	}

	final public function getInputAttribute(): InputAttribute {
		return $this->inputAttribute;
	}

	final public function hasInputAttribute(): bool {
		return isset( $this->inputAttribute );
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

	/**
	 * Sets input definitions using Positional|Associative|Flag attributes.
	 *
	 * This method may be overridden to handle attribute extraction. Make sure
	 * to update `InputAttribute` property using `$this->setInputAttribute()`
	 * if a new instance is used to handle attribute extraction & parsing.
	 *
	 * @param ReflectionClass<Console> $reflection    The late static binding class reflection, or the reflection
	 *                                                from parser if the attribute parser has already been set.
	 * @param ?InputAttribute          $defaultParser Parser instantiated by late static binding class. If it
	 *                                                is `null`, the attribute parser has already been set &
	 *                                                can be retrieved using `$this->getInputAttribute()`.
	 *
	 * ```php
	 * use ReflectionClass;
	 * use TheWebSolver\Codegarage\Cli\Data\Flag;
	 * use heWebSolver\Codegarage\Cli\Enums\InputVariant;
	 * use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;
	 *
	 * function withDefinitionsFrom(?InputAttribute $defaultParser, ReflectionClass $reflection): static {
	 *  // Using update mode instead of replacing whole parent attribute. Eg: for prop: "default" or "suggestedValues".
	 *  $mode = InputAttribute::INFER_AND_UPDATE;
	 *  // If only some attribute variants needs to be parsed, provide like so:
	 *  $variants = [InputVariant::Positional, InputVariant::Associative];
	 *
	 *  // OPTION 1: Use default or already set attribute parser.
	 *  $existingParser = $defaultParser ?? $this->getInputAttribute();
	 *
	 *  $existingParser->register($mode, ...$variants)->parse();
	 * // Remove attribute named: "cheer".
	 *  $isCheerInputRemoved = $existingParser->remove('cheer', Flag::class);
	 *  // Finally, convert to symfony inputs.
	 *  $symfonyInputs = $existingParser->toSymfonyInput($this->getDefinition());
	 *  // Ensure definitions are registered.
	 *  return $this->setInputAttribute($existingParser)->setDefined(!empty($symfonyInputs));
	 *
	 *  // OPTION 2: Ignore both default or already set attribute parser via constructor.
	 *  $newParser = InputAttribute::from($reflection)->register($mode, ...$variants)->parse();
	 *  // Add additional input before converting to input definition (in addition to class attribute).
	 *  $isCheerInputAdded = $newParser->add(new Flag('cheer', desc: 'celebrate victory!'));
	 * // Finally, convert to symfony inputs.
	 *  $symfonyInputs = $newParser->toSymfonyInput($this->getDefinition());
	 *  // Ensure definitions are registered.
	 *  return $this->setInputAttribute($newParser)->setDefined(!empty($symfonyInputs));
	 * }
	 * ```
	 */
	protected function withDefinitionsFrom( ?InputAttribute $defaultParser, ReflectionClass $reflection ): static {
		return $this->setDefined(
			! empty( ( $defaultParser ?? $this->getInputAttribute() )->parse()->toSymfonyInput( $this->getDefinition() ) )
		);
	}

	/**
	 * @param ReflectionClass<Console> $ref
	 * @return non-empty-string
	 */
	private static function commandNameFromClassname( ReflectionClass $ref ): string {
		$name = str_replace( search: '_', replace: '', subject: ucwords( $ref->getShortName(), separators: '_' ) );

		return static::CLI_NAMESPACE . ':' . lcfirst( $name );
	}

	/** @return ReflectionClass<Console> */
	private function registerCommandDetails(): ReflectionClass {
		$ref = $this->hasInputAttribute()
			? $this->getInputAttribute()->getTargetReflection()
			: new ReflectionClass( static::class );

		if ( ! $attribute = $this->getCommandAttribute( $ref ) ) {
			! ! $this->getName() || $this->setName( self::commandNameFromClassname( $ref ) );

			return $ref;
		}

		! ! $this->getName() || $this->setName( $attribute->commandName );

		// Fallback to values set by Symfony Command.
		$this->setDescription( $attribute->description ?? $this->getDescription() )
			->setHidden( $attribute->isInternal || $this->isHidden() )
			->setAliases( $attribute->altNames ?: $this->getAliases() )
			->setHelp( $attribute->help ?? $this->getHelp() );

		return $ref;
	}

	/** @param ReflectionClass<Console> $reflection */
	private function registerParsedInputsToDefinitions( ReflectionClass $reflection ): static {
		// Does not override InputAttribute if already set by inheriting class via constructor.
		$this->setInputAttribute( $this->inputAttribute ?? ( $parser = InputAttribute::from( $reflection ) ) );

		return $this->isDefined() ? $this : $this->withDefinitionsFrom( $parser ?? null, $reflection );
	}
}
