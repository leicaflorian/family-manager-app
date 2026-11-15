<?php

declare(strict_types=1);

use App\Data\ReleaseNoteData;
use App\Enums\Role;
use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\ReleaseNotes;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->notesPath = storage_path('framework/testing/release-notes-'.uniqid().'.json');
    file_put_contents($this->notesPath, json_encode([
        ['version' => '1.2.0', 'date' => '2026-11-15', 'notes' => ['it' => ['Novità uno', 'Novità due'], 'en' => ['News one', 'News two']]],
        ['version' => '1.1.0', 'date' => '2026-11-01', 'notes' => ['it' => ['Solo in italiano']]],
    ], JSON_THROW_ON_ERROR));
    app()->instance(ReleaseNotes::class, new ReleaseNotes($this->notesPath));
    config(['app.version' => '1.2.0']);

    $this->family = Family::factory()->create();
    $this->marco = User::factory()->for($this->family)->create();
});

afterEach(function (): void {
    if (is_file($this->notesPath)) {
        File::delete($this->notesPath);
    }
});

test('the settings index shows the version and the day it was released', function (): void {
    $this->actingAs($this->marco)->get(route('settings'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/Settings')
        ->where('release', '1.2.0')
        ->where('releasedOn', '2026-11-15'));
});

test('the settings index has no day for a version without notes', function (): void {
    config(['app.version' => 'dev']);

    $this->actingAs($this->marco)->get(route('settings'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('release', 'dev')
        ->where('releasedOn', null));
});

test('the pages no longer carry the version in the shared props', function (): void {
    $this->actingAs($this->marco)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->missing('release')
        ->missing('release_notes'));
});

test('What\'s new lists the versions in the language of the browser', function (): void {
    $this->actingAs($this->marco)->get(route('settings.news'), ['Accept-Language' => 'it'])->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('settings/News')
        ->where('release', '1.2.0')
        ->where('notes', [
            ['version' => '1.2.0', 'date' => '2026-11-15', 'notes' => ['Novità uno', 'Novità due']],
            ['version' => '1.1.0', 'date' => '2026-11-01', 'notes' => ['Solo in italiano']],
        ]));
});

test('a version without notes in the language of the browser shows those of the other language', function (): void {
    $this->actingAs($this->marco)->get(route('settings.news'), ['Accept-Language' => 'en'])->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('notes.0.notes', ['News one', 'News two'])
        ->where('notes.1.notes', ['Solo in italiano']));
});

test('a member sees What\'s new too', function (): void {
    $laura = User::factory()->for($this->family)->create(['role' => Role::Member]);

    $this->actingAs($laura)->get(route('settings.news'))->assertOk();
});

test('a guest is sent to the login', function (): void {
    $this->get(route('settings.news'))->assertRedirect(route('login'));
});

test('a phone linked with a code does not open What\'s new', function (): void {
    $device = LinkedDevice::factory()->for($this->family)->for($this->marco)->create(['token_hash' => hash('sha256', 'x'), 'last_used_at' => now()]);

    $this->actingAs($this->marco)->withSession(['linked_device_id' => $device->id])
        ->get(route('settings.news'))
        ->assertRedirect(route('scan'));
});

test('a missing file gives no notes', function (): void {
    expect(new ReleaseNotes($this->notesPath.'.missing')->latest('en'))->toBe([]);
});

test('a broken file gives no notes', function (): void {
    file_put_contents($this->notesPath, '{not json');

    expect(new ReleaseNotes($this->notesPath)->latest('en'))->toBe([]);
});

test('entries without version, date or notes are left out', function (): void {
    file_put_contents($this->notesPath, json_encode([
        'not an entry',
        ['version' => '1.0.1', 'date' => '2026-10-30', 'notes' => ['en' => []]],
        ['version' => 3, 'date' => '2026-10-29', 'notes' => ['en' => ['x']]],
        ['version' => '1.0.0', 'notes' => ['en' => ['x']]],
        ['version' => '0.9.0', 'date' => '2026-10-20', 'notes' => ['en' => ['Kept', 5, '']]],
    ], JSON_THROW_ON_ERROR));

    $notes = new ReleaseNotes($this->notesPath)->latest('en');

    expect($notes)->toHaveCount(1)
        ->and($notes[0])->toEqual(new ReleaseNoteData('0.9.0', '2026-10-20', ['Kept']));
});
