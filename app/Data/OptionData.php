<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An entry of a choice: the value and the text, with an optional explanation.
 */
#[TypeScript]
final class OptionData extends Data
{
    /**
     * @param  string  $value  the value that is sent
     * @param  string  $label  the text that is read
     * @param  string|null  $help  the sentence below, for example what a level can do
     * @param  string|Optional  $title  the short text a composed title uses instead of the label, for example the first name of a person; left out of the data when not given
     */
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $help = null,
        public readonly string|Optional $title = new Optional,
    ) {}

    /**
     * The options of an enum with their labels (see `HasLabel::options()`).
     *
     * @param  list<array{value: string, label: string, help?: string}>  $options  the options as the enum gives them
     * @return list<self>
     */
    public static function list(array $options): array
    {
        return array_map(static fn (array $option): self => new self($option['value'], $option['label'], $option['help'] ?? null), $options);
    }
}
