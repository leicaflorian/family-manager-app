<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * The level of an account: what it can do in its family.
 */
enum Role: string
{
    use HasLabel;

    /** Can do everything, like whoever registered the family. */
    case Admin = 'admin';

    /** Sees everything, uploads and edits documents, deletes nothing. */
    case Member = 'member';

    /**
     * The title of the level next to a person's name ("Marco Bianchi · Administrator").
     */
    public function title(): string
    {
        return self::translate('role_name', $this);
    }

    /**
     * What the level means, the sentence under the choice in *Invite a family member*.
     */
    public function description(): string
    {
        return self::translate('role_description', $this);
    }

    /**
     * The options for choosing the level, each with its description.
     *
     * @return list<array{value: string, label: string, help: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label(), 'help' => $case->description()],
            self::cases(),
        );
    }
}
