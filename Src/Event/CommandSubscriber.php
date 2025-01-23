<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Event;

use Closure;
use OutOfBoundsException;
use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\ArgvInput;
use TheWebSolver\Codegarage\Cli\Enums\InputVariant;
use Symfony\Component\Console\Completion\Suggestion;
use TheWebSolver\Codegarage\Cli\Helper\InputAttribute;
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

	/**
	 * @param Closure(CompletionInput): array<string|int, string|Suggestion>|array<string|int, string|int> $given
	 * @param ?list<string>                                                                                $argv
	 * @return array<string|int>
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public static function inputSuggestedValues( Closure|array $given, ?array $argv ): array {
		$suggestedValue = $given instanceof Closure ? $given( new CompletionInput( $argv ) ) : $given;

		return array_map( self::suggestionToString( ... ), $suggestedValue );
	}

	/** @throws OutOfBoundsException When input does not have suggested values. */
	public static function validateWithAutoComplete( Event $event ): void {
		if ( self::$disableValidation ) {
			return;
		}

		$tokens = ( $argv = $event->getInput() ) instanceof ArgvInput ? $argv->getRawTokens( true ) : null;

		foreach ( self::getSuggestions( $event ) ?? array() as $inputName => $suggestions ) {
			$suggestedValues = self::inputSuggestedValues( $suggestions, $tokens );

			if ( ! empty( $suggestedValues ) ) {
				self::validateInput( $suggestedValues, $inputName, $event );
			}
		}
	}

	/** @return ?array<string,array<string|int>|(Closure(CompletionInput): list<string|Suggestion>)> */
	private static function getSuggestions( Event $event ): ?array {
		if ( ! $parser = self::getInputAttributeParserFrom( $event ) ) {
			return null;
		}

		return ! $parser->getTargetReflection()->getAttributes( DoNotValidateSuggestedValues::class )
			? $parser->getSuggestions()
			: null;
	}

	/** @param array<string|int> $suggestions */
	private static function validateInput( array $suggestions, string $name, Event $event ): true {
		$input   = $event->getInput();
		$value   = $input->hasOption( $name ) ? $input->getOption( $name ) : $input->getArgument( $name );
		$isValid = match ( true ) {
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

		$attribute = self::getCommandFrom( $event )?->getInputAttribute()?->by( $inputName );
		$variant   = $attribute ? InputVariant::fromAttribute( $attribute::class )?->value : null;
		$input     = 'input' . ( $variant ? " {$variant}" : '' );
		$msg       = array(
			Console::COMMAND_VALUE_ERROR,
			Console::LONG_SEPARATOR,
			sprintf( 'Value does not match any of the suggested values provided for %1$s "%2$s".', $input, $inputName ),
			Console::LONG_SEPARATOR_LINE,
			'Use one of the below suggested values and try again.',
			Console::LONG_SEPARATOR_LINE,
			implode( ' | ', $suggestions ),
		);

		throw new OutOfBoundsException( implode( separator: PHP_EOL, array: $msg ) );
	}

	private static function getInputAttributeParserFrom( Event $event ): ?InputAttribute {
		return ( $command = self::getCommandFrom( $event ) ) ? $command->getInputAttribute() : null;
	}

	private static function getCommandFrom( Event $event ): ?Console {
		return ( $command = $event->getCommand() ) && $command instanceof Console ? $command : null;
	}
}
