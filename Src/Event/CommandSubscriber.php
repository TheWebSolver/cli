<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Event;

use Closure;
use ReflectionClass;
use OutOfBoundsException;
use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent as Event;
use TheWebSolver\Codegarage\Cli\Attribute\DoNotValidateSuggestedValues;

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

	public static function suggestionToString( string|int|Suggestion $suggestion ): string|int {
		return $suggestion instanceof Suggestion ? (string) $suggestion : $suggestion;
	}

	/** @throws OutOfBoundsException When input does not have suggested values. */
	public static function validateWithAutoComplete( Event $event ): void {
		if ( self::$disableValidation ) {
			return;
		}

		$tokens = ( $argv = $event->getInput() ) instanceof ArgvInput ? $argv->getRawTokens( true ) : null;

		foreach ( self::getSuggestions( $event ) ?? array() as $inputName => $suggestions ) {
			$suggestedValues = $suggestions instanceof Closure
				? $suggestions( new CompletionInput( $tokens ) )
				: $suggestions;

			if ( ! empty( $suggestedValues ) ) {
				self::validateInput( $suggestedValues, $inputName, $event );
			}
		}
	}

	/** @return ?array<string,array<string|int>|(Closure(CompletionInput): list<string|Suggestion>)> */
	private static function getSuggestions( Event $event ): ?array {
		if ( ! ( $command = $event->getCommand() ) || ! $command instanceof Console ) {
			return null;
		}

		$commandReflection  = new ReflectionClass( $command );
		$suppressValidation = $commandReflection->getAttributes( DoNotValidateSuggestedValues::class );

		return $suppressValidation ? null : $command->getInputAttribute()?->getSuggestions();
	}

	/** @param array<string|int|Suggestion> $suggestions */
	private static function validateInput( array $suggestions, string $name, Event $event ): true {
		$input       = $event->getInput();
		$value       = $input->hasOption( $name ) ? $input->getOption( $name ) : $input->getArgument( $name );
		$suggestions = array_map( self::suggestionToString( ... ), $suggestions );
		$isValid     = match ( true ) {
			// phpcs:ignore -- Strict comparison not required. Value may not always be string.
			default            => in_array( $value, $suggestions ),
			is_array( $value ) => empty( array_diff( $value, $suggestions ) ),
			is_null( $value )  => true,
		};

		return $isValid ? true : self::throwInvalidValue( $suggestions, $event, $name );
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
