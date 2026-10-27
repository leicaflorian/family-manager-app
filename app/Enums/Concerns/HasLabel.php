<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use BackedEnum;
use Illuminate\Support\Str;

/**
 * For the enums of fixed choices: the label of each value, in the current
 * language, and the list of options for the menus of the pages.
 *
 * The labels live in `lang/<locale>/enums.php`, under the enum name in snake
 * case and then the value: `enums.utility_type.waste_tax`.
 */
trait HasLabel
{
    /**
     * The value as the person reads it.
     */
    public function label(): string
    {
        return self::translate(Str::snake(class_basename(self::class)), $this);
    }

    /**
     * The options for a menu, in the order the values are declared.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * A translation of `lang/<locale>/enums.php` for one value of an enum.
     *
     * @param  string  $group  the group in the file, for example `utility_type`
     * @param  BackedEnum  $case  the value
     */
    private static function translate(string $group, BackedEnum $case): string
    {
        $key = "enums.{$group}.{$case->value}";
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
