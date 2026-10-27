<?php

declare(strict_types=1);

use App\Copy\DirectusMap;

/**
 * A fake id map: a number becomes itself plus 100, a uuid becomes 900, and it
 * remembers what it was asked.
 *
 * @param  list<string>  $asked  the tables asked, by reference
 */
function shifted(array &$asked): Closure
{
    return function (string $table, int|string $id) use (&$asked): int {
        $asked[] = "{$table}:{$id}";

        return is_numeric($id) ? (int) $id + 100 : 900;
    };
}

test('a document row gets the many-to-one fields as _id, with the new ids', function (): void {
    $asked = [];
    $row = DirectusMap::row('documents', [
        'id' => 41, 'title' => 'Busta paga', 'person' => 3, 'organization' => 7, 'category' => 2, 'file' => 'a1b2',
        'planned_expense' => null, 'status' => 'active', 'tags' => '["salary"]', 'created_at' => '2026-10-23 09:00:00',
    ], shifted($asked));

    expect($row)->toBe([
        'title' => 'Busta paga', 'person_id' => 103, 'organization_id' => 107, 'category_id' => 102, 'file_id' => 900,
        'planned_expense_id' => null, 'status' => 'active', 'tags' => '["salary"]', 'created_at' => '2026-10-23 09:00:00',
    ])->and($asked)->toContain('people:3', 'organizations:7', 'document_categories:2', 'directus_files:a1b2');
});

test('the author fields and the Directus dates get the Laravel names', function (): void {
    $asked = [];
    $row = DirectusMap::row('family_settings', ['id' => 1, 'date_updated' => '2026-10-01 10:00:00', 'mail_inbox_notify' => '[]'], shifted($asked));

    expect($row)->toBe(['updated_at' => '2026-10-01 10:00:00', 'mail_inbox_notify' => '[]']);
});

test('family_settings loses the AI keys, the models and the schedules', function (): void {
    $asked = [];
    $row = DirectusMap::row('family_settings', [
        'id' => 1, 'anthropic_api_key' => 'k', 'gemini_api_key' => 'k', 'document_reader_provider' => 'anthropic', 'document_reader_model' => 'm',
        'document_reader_crop_model' => 'm', 'reminders_cron' => '0 7 * * *', 'loans_cron' => '0 6 1 * *', 'inbox_cleanup_cron' => 'x',
        'inbox_cleanup_days' => 30, 'mail_inbox_max_megabytes' => 20, 'calendar_feed_token' => 't', 'family_name' => 'Leica',
        'mail_inbox_notify_attachments_only' => 1, 'mail_inbox_ai_filter' => 1,
        'mail_inbox_recipient' => 'bollette@example.com', 'mail_inbox_notify' => '["florian"]',
    ], shifted($asked));

    expect($row)->toBe(['mail_inbox_recipient' => 'bollette@example.com', 'mail_inbox_notify' => '["florian"]']);
});

test('people lose the upload token and get their account as user_id', function (): void {
    $asked = [];
    $row = DirectusMap::row('people', ['id' => 2, 'first_name' => 'Laura', 'user' => 'u-7', 'photo' => null, 'upload_token' => 'secret'], shifted($asked));

    expect($row)->toBe(['first_name' => 'Laura', 'user_id' => 900, 'photo_id' => null])
        ->and($asked)->toBe(['directus_users:u-7']);
});

test('a category points to its parent in the same table, and loses the Directus folder', function (): void {
    $asked = [];
    $row = DirectusMap::row('document_categories', ['id' => 9, 'name' => 'Luce', 'parent' => 4, 'folder' => 'f-1', 'sort' => 2], shifted($asked));

    expect($row)->toBe(['name' => 'Luce', 'parent_id' => 104, 'sort' => 2])->and($asked)->toBe(['document_categories:4']);
});

test('utilities_documents becomes a row of documentables of type utility', function (): void {
    $asked = [];

    expect(DirectusMap::target('utilities_documents'))->toBe('documentables')
        ->and(DirectusMap::row('utilities_documents', ['id' => 1, 'utilities_id' => 3, 'documents_id' => 12], shifted($asked)))
        ->toBe(['document_id' => 112, 'documentable_type' => 'utility', 'documentable_id' => 103]);
});

test('reminders_related_to with collection vehicles becomes a remindable of type vehicle', function (): void {
    $asked = [];

    expect(DirectusMap::target('reminders_related_to'))->toBe('remindables')
        ->and(DirectusMap::row('reminders_related_to', ['id' => 1, 'reminders_id' => 5, 'collection' => 'vehicles', 'item' => '2'], shifted($asked)))
        ->toBe(['reminder_id' => 105, 'remindable_type' => 'vehicle', 'remindable_id' => 102])
        ->and($asked)->toBe(['reminders:5', 'vehicles:2']);
});

test('the order copies first the tables the others point to, and the links at the end', function (): void {
    $order = DirectusMap::order();

    expect(array_slice($order, 0, 4))->toBe(['directus_files', 'directus_users', 'organizations', 'document_categories'])
        ->and(array_search('people', $order, true))->toBeLessThan(array_search('vehicles', $order, true))
        ->and(array_search('planned_expenses', $order, true))->toBeLessThan(array_search('expenses', $order, true))
        ->and(array_search('expenses', $order, true))->toBeLessThan(array_search('health_events', $order, true))
        ->and(array_search('planned_expenses', $order, true))->toBeLessThan(array_search('documents', $order, true))
        ->and(array_slice($order, -16))->each->toMatch('/_documents$|^reminders_related_to$/')
        ->and($order)->not->toContain('ai_readings', 'mail_inbox_calls');

    foreach (DirectusMap::order() as $table) {
        foreach (DirectusMap::relations($table) as $target) {
            expect(array_search($target, $order, true))->not->toBeFalse("{$table} points to {$target}, which is not copied");
        }
    }
});
