<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Enums\VehicleStatus;
use App\Models\Document;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->member = User::factory()->create(['family_id' => $this->marco->family_id, 'role' => Role::Member, 'email_verified_at' => now()]);
});

afterEach(fn () => Date::setTestNow());

/**
 * The expired health card of Giulia, 20/10/2026, with its open automatic reminder.
 */
function expiredHealthCard(): Document
{
    return insideBianchi(fn (): Document => Document::query()->whereDate('expiry_date', '2026-10-20')->sole());
}

/**
 * The automatic reminders of a document, in the family of the Bianchi.
 *
 * @return list<array{due_date: string, status: string}>
 */
function remindersOfDocument(Document $document): array
{
    return insideBianchi(fn (): array => Reminder::query()->automaticFor('documents.expiry_date', $document->getMorphClass(), [$document->id])->orderBy('due_date')->get()
        ->map(static fn (Reminder $reminder): array => ['due_date' => $reminder->due_date->toDateString(), 'status' => $reminder->status->value])->all());
}

test('Update the date writes the new date on the document, which is no longer expired', function (): void {
    $card = expiredHealthCard();

    $this->actingAs($this->marco)->put("/agenda/deadlines/documents.expiry_date/{$card->id}", ['date' => '2031-10-20'])->assertRedirect();

    $card->refresh();
    expect($card->expiry_date?->toDateString())->toBe('2031-10-20')
        ->and($card->status)->toBe(DocumentStatus::Active);
});

test('Update the date does not mark the reminder done: the old one goes and a new one waits on the new date', function (): void {
    $card = expiredHealthCard();

    $this->actingAs($this->marco)->put("/agenda/deadlines/documents.expiry_date/{$card->id}", ['date' => '2031-10-20']);

    expect(remindersOfDocument($card))->toBe([['due_date' => '2031-10-20', 'status' => 'open']]);
});

test('Renewed also marks the reminder of the old date done', function (): void {
    $card = expiredHealthCard();

    $this->actingAs($this->marco)->put("/agenda/deadlines/documents.expiry_date/{$card->id}", ['date' => '2031-10-20', 'renewed' => true]);

    expect(remindersOfDocument($card))->toBe([
        ['due_date' => '2026-10-20', 'status' => 'done'],
        ['due_date' => '2031-10-20', 'status' => 'open'],
    ]);
});

test('the date of a card changes its own column', function (): void {
    $panda = insideBianchi(fn (): Vehicle => Vehicle::query()->where('status', VehicleStatus::Active)->whereNotNull('road_tax_due_date')->firstOrFail());

    $this->actingAs($this->marco)->put("/agenda/deadlines/vehicles.road_tax_due_date/{$panda->id}", ['date' => '2027-01-31'])->assertRedirect();

    expect($panda->refresh()->road_tax_due_date?->toDateString())->toBe('2027-01-31');
});

test('the date is required and must be a day', function (mixed $date): void {
    $card = expiredHealthCard();

    $this->actingAs($this->marco)->put("/agenda/deadlines/documents.expiry_date/{$card->id}", ['date' => $date])->assertSessionHasErrors('date');

    expect($card->refresh()->expiry_date?->toDateString())->toBe('2026-10-20');
})->with(['', '20/10/2031', 'domani']);

test('a member can change the date of a document, which they can edit', function (): void {
    $card = expiredHealthCard();

    $this->actingAs($this->member)->put("/agenda/deadlines/documents.expiry_date/{$card->id}", ['date' => '2031-10-20'])->assertRedirect();

    expect($card->refresh()->expiry_date?->toDateString())->toBe('2031-10-20');
});

test('a member cannot change the date of a card, which they cannot edit', function (): void {
    $panda = insideBianchi(fn (): Vehicle => Vehicle::query()->whereNotNull('road_tax_due_date')->firstOrFail());

    $this->actingAs($this->member)->put("/agenda/deadlines/vehicles.road_tax_due_date/{$panda->id}", ['date' => '2027-01-31'])->assertForbidden();
});

test('an unknown kind of deadline or a card of another family is not found, even with a wrong body', function (string $field): void {
    $card = expiredHealthCard();
    $other = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($other)->put("/agenda/deadlines/{$field}/{$card->id}", ['date' => 'never'])->assertNotFound();
})->with(['documents.expiry_date', 'documents.title']);

test('the entries of the Agenda carry the address to change their date, only to who may change it', function (): void {
    $card = expiredHealthCard();
    // A deadline of a card with no payment, which a member cannot edit: the end of a warranty
    $warranty = collect(test()->actingAs($this->marco)->get('/agenda?months=12')->viewData('page')['props']['agenda']['entries'])
        ->first(static fn (array $entry): bool => str_starts_with($entry['key'], 'deadline:belongings.warranty_expiry_date:'))['key'];
    $url = static fn (User $user, string $key): ?string => collect(test()->actingAs($user)->get('/agenda?months=12')->viewData('page')['props']['agenda']['entries'])
        ->first(static fn (array $entry): bool => str_starts_with($entry['key'], $key), ['update_url' => 'missing'])['update_url'];

    expect($url($this->marco, "deadline:documents.expiry_date:{$card->id}:"))->toBe(route('agenda.deadlines.update', ['field' => 'documents.expiry_date', 'id' => $card->id]))
        ->and($url($this->member, "deadline:documents.expiry_date:{$card->id}:"))->not->toBeNull()
        ->and($url($this->marco, $warranty))->toStartWith(url('/agenda/deadlines/belongings.warranty_expiry_date/'))
        ->and($url($this->member, $warranty))->toBeNull()
        ->and(collect(test()->actingAs($this->marco)->get('/agenda')->viewData('page')['props']['agenda']['entries'])->firstWhere('kind', 'income')['update_url'])->toBeNull();
});

test('a new reminder is born only for this family', function (): void {
    $card = expiredHealthCard();
    $this->actingAs($this->marco)->put("/agenda/deadlines/documents.expiry_date/{$card->id}", ['date' => '2031-10-20']);

    expect(Reminder::query()->withoutGlobalScopes()->whereDate('due_date', '2031-10-20')->pluck('family_id')->unique()->all())
        ->toBe([insideBianchi(static fn (): int => CurrentFamily::id())]);
});
