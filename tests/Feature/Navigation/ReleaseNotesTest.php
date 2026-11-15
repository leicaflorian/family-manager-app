<?php

declare(strict_types=1);

use App\Data\ReleaseNoteData;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\ReleaseNotes;
use Illuminate\Http\Request;
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

/**
 * The headers of a partial reload of the release notes from a page.
 *
 * @return array<string, string>
 */
function releaseNotesReload(string $component, string $language = 'en'): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => 'release_notes',
        'Accept-Language' => $language,
    ];
}

test('a visit to a page carries the version but not the release notes', function (): void {
    $this->actingAs($this->marco)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Home')
        ->where('release', '1.2.0')
        ->missing('release_notes'));
});

test('the version is dev when the app has none', function (): void {
    config(['app.version' => null]);

    $this->actingAs($this->marco)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('release', 'dev'));
});

test('a partial reload brings the release notes in the language of the browser', function (): void {
    $props = $this->actingAs($this->marco)->get(route('areas'), releaseNotesReload('Areas', 'it'))->assertOk()->json('props');

    expect($props['release_notes'])->toBe([
        ['version' => '1.2.0', 'date' => '2026-11-15', 'notes' => ['Novità uno', 'Novità due']],
        ['version' => '1.1.0', 'date' => '2026-11-01', 'notes' => ['Solo in italiano']],
    ]);
});

test('a version without notes in the language of the browser shows those of the other language', function (): void {
    $props = $this->actingAs($this->marco)->get(route('areas'), releaseNotesReload('Areas'))->assertOk()->json('props');

    expect($props['release_notes'][0]['notes'])->toBe(['News one', 'News two'])
        ->and($props['release_notes'][1]['notes'])->toBe(['Solo in italiano']);
});

test('a phone linked with a code receives neither the version nor the release notes', function (): void {
    $device = LinkedDevice::factory()->for($this->family)->for($this->marco)->create(['token_hash' => hash('sha256', 'x'), 'last_used_at' => now()]);

    $props = $this->actingAs($this->marco)->withSession(['linked_device_id' => $device->id])
        ->get(route('scan'), releaseNotesReload('scan/Scan'))
        ->assertOk()
        ->json('props');

    expect($props)->not->toHaveKey('release_notes')->not->toHaveKey('release');
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
