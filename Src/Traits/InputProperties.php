<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Cli\Traits;

use Closure;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Completion\CompletionInput;

trait InputProperties {
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
}
