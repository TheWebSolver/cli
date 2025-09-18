<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use Closure;
use BackedEnum;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;

/** @template TParamNames of string */
trait InputProperties {
	/** @use ConstructorAware<TParamNames> */
	use ConstructorAware, PureArg;

	/** @var string Input name. */
	public readonly string $name;
	/** @var int Input mode. */
	public readonly int $mode;
	/** @var string Input short description. */
	public readonly string $desc;
	/** @var bool Whether input can be repeated or not. If set to true, multiple values can be passed for same input name. */
	public readonly bool $isVariadic;
	/** @var bool Whether input value can be omitted. If set to false, input requires a value, else uses **default** value (if provided). */
	public readonly bool $isOptional;
	/** @var array<string|int>|(Closure(CompletionInput): list<string|Suggestion>) The input suggested values. */
	public readonly array|Closure $suggestedValues;
	/** @var null|string|bool|int|float|array{} The default value to use if input **isOptional**. */
	public readonly null|string|bool|int|float|array $default;
	/** @var null|string|class-string<BackedEnum>|bool|int|float|array{}|(callable(): string|bool|int|float|array{}) */
	private mixed $userDefault;

	/**
	 * @param string $name       Input name.
	 * @param string $desc       Input short description.
	 * @param bool   $isVariadic Whether input can be repeated or not. If set to true,
	 *                           multiple values can be passed for same input name.
	 * @param bool   $isOptional Whether input value can be omitted. If set to false, input requires
	 *                           a value, else uses **default** value (if provided).
	 * @param (
	 *   string|class-string<BackedEnum>|BackedEnum|bool|int|float|array{}|(callable(): string|bool|int|float|array{})
	 * ) $default         The default value to use if input **isOptional**.
	 * @param (
	 *   class-string<BackedEnum>|array<string|int>|callable(CompletionInput): list<string|Suggestion>
	 * ) $suggestedValues The input suggested values.
	 */
	public function __construct(
		string $name,
		?string $desc = null,
		?bool $isVariadic = null,
		?bool $isOptional = null,
		null|string|bool|int|float|array|callable|BackedEnum $default = null,
		null|string|array|callable $suggestedValues = null
	) {
		$this->paramNames    ??= $this->discoverPureFrom( methodName: __FUNCTION__, values: func_get_args() );
		$this->userDefault     = $default = $default instanceof BackedEnum ? $default->value : $default;
		$this->name            = strtolower( $name );
		$this->desc            = $desc ?? '';
		$this->isVariadic      = $isVariadic ?? $this->isVariadic();
		$this->isOptional      = $isOptional ?? $this->isOptional();
		$this->mode            = $this->normalizeMode();
		$this->default         = $this->normalizeDefault( $default );
		$this->suggestedValues = Parser::parseInputSuggestion( $suggestedValues ?? [] );
	}

	public function __toString(): string {
		return $this->name;
	}

	/** @return array<TParamNames,mixed> */
	public function __debugInfo(): array {
		return $this->mapConstructor( withParamNames: true );
	}

	/** @return null|string|bool|int|float|array{}|class-string<BackedEnum>|(callable(): string|bool|int|float|array{}) */
	public function getUserDefault(): null|string|bool|int|float|array|callable {
		return $this->userDefault;
	}

	private function isVariadic(): bool {
		return false;
	}

	private function isOptional(): bool {
		return true;
	}

	/** @return array<string|int> */
	private function variadicFromEnum( mixed $maybeBackedEnumClass ): ?array {
		return is_string( $maybeBackedEnumClass )
			? ( is_array( $cases = Parser::parseBackedEnumValue( $maybeBackedEnumClass ) ) ? $cases : null )
			: null;
	}

	abstract public function with( array $args ): self;

	abstract protected function normalizeMode(): int;
	/** @return null|string|bool|int|float|array{} */
	abstract protected function normalizeDefault( mixed $default ): null|string|bool|int|float|array;
}
