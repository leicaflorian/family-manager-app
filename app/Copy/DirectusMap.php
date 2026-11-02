<?php

declare(strict_types=1);

namespace App\Copy;

use App\Support\Linkables;
use App\Support\Reminders\NotificationRule;
use InvalidArgumentException;

/**
 * How the tables of Directus become those of the app: in which order they are
 * copied, which fields are renamed and which are left out.
 *
 * The rules of the names are those of the schema: a many-to-one becomes
 * `<field>_id` with the new id, `date_created` and `date_updated` become
 * `created_at` and `updated_at`, `user_created` and `user_updated` become
 * `created_by` and `updated_by`. The 15 junction tables of the documents
 * become `documentables`, `reminders_related_to` becomes `remindables`.
 */
final class DirectusMap
{
    /** The tables of Directus, in the order they are copied: first those the others point to. */
    private const array RECORDS = [
        'directus_files', 'directus_users', 'organizations', 'document_categories', 'expense_categories', 'people',
        'homes', 'vehicles', 'financial_accounts', 'employments', 'utilities', 'belongings', 'insurance_policies',
        'loans', 'filings', 'enrollments', 'subscriptions', 'planned_expenses', 'planned_expense_exceptions',
        'expenses', 'health_events', 'maintenance_records', 'planned_incomes', 'planned_income_exceptions', 'incomes',
        'documents', 'reminder_groups', 'reminders', 'family_settings',
    ];

    /** The table each many-to-one field points to, the same in every table. */
    private const array RELATIONS = [
        'person' => 'people', 'organization' => 'organizations', 'file' => 'directus_files', 'photo' => 'directus_files',
        'logo' => 'directus_files', 'user' => 'directus_users', 'home' => 'homes', 'vehicle' => 'vehicles',
        'employment' => 'employments', 'financial_account' => 'financial_accounts', 'planned_expense' => 'planned_expenses',
        'planned_income' => 'planned_incomes', 'utility' => 'utilities', 'insurance_policy' => 'insurance_policies',
        'loan' => 'loans', 'enrollment' => 'enrollments', 'subscription' => 'subscriptions', 'belonging' => 'belongings',
        'expense' => 'expenses', 'reminder_group' => 'reminder_groups',
    ];

    /** The many-to-one fields that point to a different table in each table. */
    private const array TABLE_RELATIONS = [
        'documents' => ['category' => 'document_categories'],
        'expenses' => ['category' => 'expense_categories'],
        'planned_expenses' => ['category' => 'expense_categories'],
        'document_categories' => ['parent' => 'document_categories'],
        'expense_categories' => ['parent' => 'expense_categories'],
    ];

    /** The author fields: they point to the accounts, with the Laravel name. */
    private const array AUTHORS = ['user_created' => 'created_by', 'user_updated' => 'updated_by'];

    /** The Directus dates, with the Laravel name. */
    private const array DATES = ['date_created' => 'created_at', 'date_updated' => 'updated_at'];

    /** The fields left out everywhere: the id (the app gives new ones), the Directus folders and the upload links. */
    private const array DROPPED = ['id', 'folder', 'upload_token'];

    /** The fields left out of one table: they belong to whoever sells the app, or are gone. */
    private const array TABLE_DROPPED = [
        'family_settings' => [
            'reminders_cron', 'loans_cron', 'inbox_cleanup_cron', 'inbox_cleanup_days', 'mail_inbox_max_megabytes',
            'document_reader_provider', 'document_reader_model', 'document_reader_crop_model', 'anthropic_api_key',
            'gemini_api_key', 'calendar_feed_token', 'family_name', 'mail_inbox_notify_attachments_only', 'mail_inbox_ai_filter',
        ],
        'reminder_groups' => ['settings'],
    ];

    /** The system tables of Directus: only these fields are copied, with the name of the app. */
    private const array SYSTEM = [
        'directus_files' => ['filename_download' => 'original_name', 'type' => 'mime_type', 'filesize' => 'size', 'uploaded_on' => 'created_at'],
        'directus_users' => ['email' => 'email'],
    ];

    /** The card types a planned expense had a column for in Directus, in the order the first one filled wins. */
    private const array PLANNED_CARDS = ['home', 'utility', 'vehicle', 'insurance_policy', 'loan', 'enrollment', 'subscription'];

    /** The table of reminders' links. */
    private const string REMINDER_LINKS = 'reminders_related_to';

    /**
     * The tables of Directus in the order they are copied: the records, then
     * the junction tables of the documents and the links of the reminders.
     *
     * @return list<string>
     */
    public static function order(): array
    {
        return [...self::RECORDS, ...self::junctions(), self::REMINDER_LINKS];
    }

    /**
     * The table of the app a Directus table is copied into.
     *
     * @param  string  $table  the Directus table
     */
    public static function target(string $table): string
    {
        return match (true) {
            $table === 'directus_files' => 'files',
            $table === 'directus_users' => 'users',
            $table === self::REMINDER_LINKS => 'remindables',
            in_array($table, self::junctions(), true) => 'documentables',
            default => $table,
        };
    }

    /**
     * The many-to-one fields of a Directus table, with the table they point to.
     *
     * @param  string  $table  the Directus table
     * @return array<string, string> the Directus field => the Directus table it points to
     */
    public static function relations(string $table): array
    {
        if ($table === self::REMINDER_LINKS) {
            return ['reminders_id' => 'reminders'];
        }

        $collection = self::collectionOf($table);

        if ($collection !== null) {
            return ["{$collection}_id" => $collection, 'documents_id' => 'documents'];
        }

        return [
            ...array_fill_keys(array_keys(self::AUTHORS), 'directus_users'),
            ...self::RELATIONS,
            ...self::TABLE_RELATIONS[$table] ?? [],
        ];
    }

    /**
     * A row of Directus as the app writes it, without `id` and `family_id`.
     *
     * @param  string  $table  the Directus table
     * @param  array<string, mixed>  $row  the row as Directus has it
     * @param  callable(string, int|string): ?int  $newId  the new id of a row of a Directus table, or null if it was not copied
     * @return array<string, mixed>
     */
    public static function row(string $table, array $row, callable $newId): array
    {
        if ($table === self::REMINDER_LINKS) {
            $type = is_string($row['collection'] ?? null) ? Linkables::typeOfCollection($row['collection']) : null;

            return [
                'reminder_id' => self::idOf($newId, 'reminders', $row['reminders_id'] ?? null),
                'remindable_type' => $type,
                'remindable_id' => $type === null ? null : self::idOf($newId, (string) $row['collection'], $row['item'] ?? null),
            ];
        }

        $collection = self::collectionOf($table);

        if ($collection !== null) {
            return [
                'document_id' => self::idOf($newId, 'documents', $row['documents_id'] ?? null),
                'documentable_type' => Linkables::typeOfCollection($collection),
                'documentable_id' => self::idOf($newId, $collection, $row["{$collection}_id"] ?? null),
            ];
        }

        return isset(self::SYSTEM[$table]) ? self::system($table, $row) : self::record($table, $row, $newId);
    }

    /**
     * A row of a table of records: renamed and with the new ids.
     *
     * @param  string  $table  the Directus table
     * @param  array<string, mixed>  $row  the row as Directus has it
     * @param  callable(string, int|string): ?int  $newId  the new id of a row
     * @return array<string, mixed>
     */
    private static function record(string $table, array $row, callable $newId): array
    {
        $relations = [...self::RELATIONS, ...self::TABLE_RELATIONS[$table] ?? []];
        $dropped = [...self::DROPPED, ...self::TABLE_DROPPED[$table] ?? []];
        $copy = [];

        foreach ($row as $field => $value) {
            if (in_array($field, $dropped, true)) {
                continue;
            }

            if (isset($relations[$field])) {
                $copy["{$field}_id"] = self::idOf($newId, $relations[$field], $value);
            } elseif (isset(self::AUTHORS[$field])) {
                $copy[self::AUTHORS[$field]] = self::idOf($newId, 'directus_users', $value);
            } else {
                $copy[self::DATES[$field] ?? $field] = $value;
            }
        }

        return match ($table) {
            'planned_expenses' => self::cardPair($copy),
            'reminder_groups' => self::groupRecipients($copy, $newId),
            default => $copy,
        };
    }

    /**
     * Replaces the Directus ids of the people in the recipients of a group's notifications with the new ids. A person
     * that was not copied is dropped; `all` and the other fields stay as they are.
     *
     * @param  array<string, mixed>  $copy  the row of the group
     * @param  callable(string, int|string): ?int  $newId  the new id of a row
     * @return array<string, mixed> the row with the notifications as a JSON text
     */
    private static function groupRecipients(array $copy, callable $newId): array
    {
        $notifications = is_string($copy['notifications'] ?? null) ? json_decode($copy['notifications'], true) : ($copy['notifications'] ?? null);

        if (! is_array($notifications)) {
            return $copy;
        }

        foreach ($notifications as &$notification) {
            if (! is_array($notification) || ! is_array($notification['recipients'] ?? null)) {
                continue;
            }

            $recipients = array_values(array_filter(array_map(
                static fn (mixed $recipient): int|string|null => match (true) {
                    $recipient === NotificationRule::ALL => NotificationRule::ALL,
                    is_int($recipient) || (is_string($recipient) && ctype_digit($recipient)) => $newId('people', $recipient),
                    default => null,
                },
                $notification['recipients'],
            ), static fn (int|string|null $recipient): bool => $recipient !== null));
            // Everybody wins over the people, and nobody left reads as everybody, like a person without an account does
            $notification['recipients'] = $recipients === [] || in_array(NotificationRule::ALL, $recipients, true) ? [NotificationRule::ALL] : $recipients;
        }

        return [...$copy, 'notifications' => json_encode($notifications, JSON_THROW_ON_ERROR)];
    }

    /**
     * Replaces the seven card columns of a planned expense, already translated to new ids, with the morph pair.
     * The first card filled wins; a card that was not copied counts as empty.
     *
     * @param  array<string, mixed>  $copy  the row with `<type>_id` columns
     * @return array<string, mixed> the row with `card_type` and `card_id` instead
     */
    private static function cardPair(array $copy): array
    {
        $pair = ['card_type' => null, 'card_id' => null];

        foreach (self::PLANNED_CARDS as $type) {
            if ($pair['card_type'] === null && isset($copy["{$type}_id"])) {
                $pair = ['card_type' => Linkables::typeOf(Linkables::TYPES[$type]), 'card_id' => $copy["{$type}_id"]];
            }

            unset($copy["{$type}_id"]);
        }

        return [...$copy, ...$pair];
    }

    /**
     * A row of a system table of Directus: only the fields the app has.
     *
     * @param  string  $table  `directus_files` or `directus_users`
     * @param  array<string, mixed>  $row  the row as Directus has it
     * @return array<string, mixed>
     */
    private static function system(string $table, array $row): array
    {
        $copy = [];

        foreach (self::SYSTEM[$table] as $field => $column) {
            $copy[$column] = $row[$field] ?? null;
        }

        return $copy;
    }

    /**
     * The new id of a value of a many-to-one field.
     *
     * @param  callable(string, int|string): ?int  $newId  the new id of a row
     * @param  string  $table  the Directus table it points to
     * @param  mixed  $value  the old id, or null
     */
    private static function idOf(callable $newId, string $table, mixed $value): ?int
    {
        return is_int($value) || is_string($value) ? $newId($table, $value) : null;
    }

    /**
     * The 15 junction tables of the documents, `<collection>_documents`.
     *
     * @return list<string>
     */
    private static function junctions(): array
    {
        return array_map(static fn (string $relation): string => self::collection($relation).'_documents', array_values(Linkables::WITH_DOCUMENTS));
    }

    /**
     * The collection of a junction table of the documents, or null if the table is not one.
     *
     * @param  string  $table  the Directus table
     */
    private static function collectionOf(string $table): ?string
    {
        return in_array($table, self::junctions(), true) ? substr($table, 0, -strlen('_documents')) : null;
    }

    /**
     * The Directus collection of a link relation of the document: `maintenanceRecords` is `maintenance_records`.
     *
     * @param  string  $relation  a value of `Linkables::WITH_DOCUMENTS`
     *
     * @throws InvalidArgumentException if the relation has no collection
     */
    private static function collection(string $relation): string
    {
        foreach (Linkables::WITH_DOCUMENTS as $type => $name) {
            if ($name === $relation) {
                return (new (Linkables::TYPES[$type]))->getTable();
            }
        }

        throw new InvalidArgumentException("No collection for the relation {$relation}.");
    }
}
