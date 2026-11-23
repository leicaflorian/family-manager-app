<?php

declare(strict_types=1);

use App\Models\Reminder;

test('an automatic reminder has the kind of deadline of its field', function (): void {
    $reminder = new Reminder(['is_automatic' => true, 'source_field' => 'vehicles.inspection_due_date']);

    expect($reminder->deadlineSource()?->title)->toBe('Get the inspection done');
});

test('a reminder written by hand has no kind of deadline, even with a field', function (): void {
    $reminder = new Reminder(['is_automatic' => false, 'source_field' => 'vehicles.inspection_due_date']);

    expect($reminder->deadlineSource())->toBeNull();
});

test('an automatic reminder without a field, or with an unknown one, has no kind of deadline', function (): void {
    expect(new Reminder(['is_automatic' => true, 'source_field' => null])->deadlineSource())->toBeNull()
        ->and(new Reminder(['is_automatic' => true, 'source_field' => 'nothing.here'])->deadlineSource())->toBeNull();
});
