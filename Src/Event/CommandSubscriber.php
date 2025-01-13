<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Event;

use Closure;
use ReflectionClass;
use ReflectionAttribute;
use OutOfBoundsException;
use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\ArgvInput;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent as Event;

class CommandSubscriber implements EventSubscriberInterface {
	private static bool $disableValidation = false;

	public static function disableSuggestionValidation( bool $disable = true ): void {
		self::$disableValidation = $disable;
	}

	public static function getSubscribedEvents() {
		return array(
			ConsoleEvents::COMMAND => array(
				array( 'validateWithAutoComplete', -1 ),
			),
		);
	}

	/** @throws OutOfBoundsException When input does not have suggested values. */
	public static function validateWithAutoComplete( Event $event ): void {
		if ( self::$disableValidation ) {
			return;
		}

		$tokens = ( $argv = $event->getInput() ) instanceof ArgvInput ? $argv->getRawTokens( true ) : null;

		foreach ( self::getAttributeWithSuggestion( $event ) ?? array() as $attribute ) {
			$input           = $attribute->newInstance();
			$suggestions     = $input->suggestedValues;
			$suggestedValues = $suggestions instanceof Closure
				? $suggestions( new CompletionInput( $tokens ) )
				: $suggestions;

			if ( ! empty( $suggestedValues ) ) {
				self::validateInput( $suggestedValues, $input, $event );
			}
		}
	}

	/** @return ?ReflectionAttribute<Positional|Associative>[] */
	private static function getAttributeWithSuggestion( Event $event ): ?array {
		if ( ! $command = $event->getCommand() ) {
			return null;
		}

		$reflection = new ReflectionClass( $command );

		return array(
			...$reflection->getAttributes( Positional::class ),
			...$reflection->getAttributes( Associative::class ),
		);
	}

	/** @param array<string|int|Suggestion> $suggestions */
	private static function validateInput( array $suggestions, Positional|Associative $input, Event $event ): true {
		$value = $input instanceof Associative
			? $event->getInput()->getOption( $input->name )
			: $event->getInput()->getArgument( $input->name );

		// phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse -- Not all suggestions are given in string.
		return null === $value || in_array( $value, $suggestions, strict: false )
			? true
			: self::throwInvalidValue( $suggestions, $event, $input->name );
	}

	/**
	 * @param array<string|int|Suggestion> $suggestions
	 * @throws OutOfBoundsException When doesn't match with suggested values.
	 */
	private static function throwInvalidValue( array $suggestions, Event $event, string $inputName ): never {
		// Instead of setting hardcoded exit here, we'll let the application to exit the console.
		$event->disableCommand();
		// And, prevent any other event listeners to be able to execute the command.
		$event->stopPropagation();

		$msg = array(
			Console::COMMAND_VALUE_ERROR,
			Console::LONG_SEPARATOR,
			sprintf( 'Value does not match any of the suggested values provided for option "%s".', $inputName ),
			Console::LONG_SEPARATOR_LINE,
			'Use one of the below suggested values and try again.',
			Console::LONG_SEPARATOR_LINE,
			implode( ' | ', $suggestions ),
		);

		throw new OutOfBoundsException( implode( separator: PHP_EOL, array: $msg ) );
	}
}
