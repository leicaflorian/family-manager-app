<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Support\Linkables;
use App\Support\ModelId;
use Illuminate\Database\Eloquent\Model;

/**
 * How the thing a reminder refers to is named and where its page is: written once for the pages, the mail and
 * the calendar.
 */
final class SubjectLink
{
    /**
     * The record a written reminder can refer to: the ones a document can link to (the cards, the expenses, the incomes).
     *
     * @return list<string> the morph aliases
     */
    public static function types(): array
    {
        return array_keys(Linkables::WITH_DOCUMENTS);
    }

    /**
     * The record of a `type:id` text, only when the type is one a reminder can refer to and the record is of the family.
     *
     * @param  mixed  $value  the text sent, for example `vehicle:12`
     * @return array{type: string, id: int}|null
     */
    public static function find(mixed $value): ?array
    {
        if (! is_string($value) || preg_match('/^([a-z_]+):(\d+)$/', $value, $parts) !== 1 || ! in_array($parts[1], self::types(), true)) {
            return null;
        }

        $class = Linkables::TYPES[$parts[1]];

        return $class::query()->whereKey((int) $parts[2])->exists() ? ['type' => $parts[1], 'id' => (int) $parts[2]] : null;
    }

    /**
     * The name of a thing among the others: its `label()` (cards) or its title (documents).
     *
     * @param  Model  $subject  the record the reminder refers to
     */
    public static function name(Model $subject): string
    {
        return match (true) {
            method_exists($subject, 'label') => (string) $subject->label(),
            isset($subject->title) => (string) $subject->title,
            default => '',
        };
    }

    /**
     * The page of a card or a document, null for other records.
     *
     * @param  string  $type  the morph alias of the record
     * @param  Model  $subject  the record
     * @param  bool  $absolute  whether the address has the host (for mails) or not (for the pages)
     */
    public static function href(string $type, Model $subject, bool $absolute = false): ?string
    {
        return match (true) {
            CardTypes::find($type) instanceof CardType => route('cards.show', ['type' => $type, 'id' => ModelId::of($subject)], $absolute),
            $type === 'document' => route('documents.show', ['document' => ModelId::of($subject)], $absolute),
            default => null,
        };
    }
}
