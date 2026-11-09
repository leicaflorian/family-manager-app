<?php

declare(strict_types=1);

use App\Enums\FileSource;
use App\Models\Family;
use App\Models\File;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    Storage::fake('local');
    $this->family = Family::factory()->create();
    $this->laura = User::factory()->for($this->family)->admin()->create();
});

afterEach(fn () => CurrentFamily::clear());

/**
 * The files of the family, whatever the family in use.
 *
 * @return Collection<int, File>
 */
function savedFiles(): Collection
{
    return File::query()->withoutGlobalScopes()->orderBy('id')->get();
}

test('a JPEG and a PDF are saved to sort, from the phone, with the note and the name of Rome', function (): void {
    $response = $this->actingAs($this->laura)->postJson(route('scan.store'), [
        'files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')],
        'note' => 'Ricevute',
    ])->assertOk();

    $files = savedFiles();
    expect($files)->toHaveCount(2)
        ->and($files[0]->original_name)->toBe('Telefono 2026-10-25 19.04.jpg')
        ->and($files[1]->original_name)->toBe('Telefono 2026-10-25 19.04.pdf')
        ->and($files->every(fn (File $f): bool => $f->to_sort && $f->source === FileSource::Phone && $f->note === 'Ricevute' && $f->family_id === $this->family->id))->toBeTrue();
    $response->assertJsonPath('files.0.id', $files[0]->id)
        ->assertJsonPath('files.0.name', 'Telefono 2026-10-25 19.04.jpg')
        ->assertJsonPath('files.1.size', $files[1]->size);
});

test('an upload at 23:30 of Rome on the last day of October is called after Rome', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-31 22:30', 'UTC'));

    $this->actingAs($this->laura)->postJson(route('scan.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertOk();

    expect(savedFiles()->sole()->original_name)->toBe('Telefono 2026-10-31 23.30.pdf');
});

test('two uploads in the same minute take a number', function (): void {
    foreach ([1, 2] as $_) {
        $this->actingAs($this->laura)->postJson(route('scan.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertOk();
    }

    expect(savedFiles()->pluck('original_name')->all())->toBe(['Telefono 2026-10-25 19.04.pdf', 'Telefono 2026-10-25 19.04 (2).pdf']);
});

test('a text file is refused', function (): void {
    $this->actingAs($this->laura)->postJson(route('scan.store'), ['files' => [UploadedFile::fake()->create('a.txt', 1, 'text/plain')]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
    expect(savedFiles())->toBeEmpty();
});

test('a file over 25 MB is refused', function (): void {
    $this->actingAs($this->laura)->postJson(route('scan.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 25601, 'application/pdf')]])
        ->assertUnprocessable()->assertJsonValidationErrors('files.0');
});

test('the files must be a list, and the note and the source are checked', function (): void {
    $pdf = fn () => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf');

    $this->actingAs($this->laura)->postJson(route('scan.store'), ['files' => ['foo' => $pdf()]])->assertUnprocessable();
    $this->actingAs($this->laura)->postJson(route('scan.store'), ['files' => [$pdf()], 'note' => str_repeat('a', 201)])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->actingAs($this->laura)->postJson(route('scan.store'), ['files' => [$pdf()], 'source' => 'mail'])->assertUnprocessable()->assertJsonValidationErrors('source');
});

test('the scanner source uses the name written', function (): void {
    $this->actingAs($this->laura)->postJson(route('scan.store'), [
        'files' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        'source' => 'scanner',
        'name' => 'polizza casa.pdf',
    ])->assertOk();

    $file = savedFiles()->sole();
    expect($file->original_name)->toBe('polizza casa.pdf')->and($file->source)->toBe(FileSource::Scanner);
});

test('the phone does not choose a name', function (): void {
    $this->actingAs($this->laura)->postJson(route('scan.store'), [
        'files' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        'name' => 'mine',
    ])->assertOk();

    expect(savedFiles()->sole()->original_name)->toBe('Telefono 2026-10-25 19.04.pdf');
});

test('a linked phone uploads into its family and marks its last upload', function (): void {
    $device = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['last_used_at' => now()]);

    $this->actingAs($this->laura)->withSession(['linked_device_id' => $device->id])
        ->postJson(route('scan.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertOk();

    expect(savedFiles()->sole()->family_id)->toBe($this->family->id)
        ->and($device->refresh()->last_upload_at?->equalTo(now()))->toBeTrue();
});

test('a member can upload', function (): void {
    $rosa = User::factory()->for($this->family)->create();

    $this->actingAs($rosa)->postJson(route('scan.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertOk();
});

test('without an account the person goes to the login', function (): void {
    $this->post(route('scan.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertRedirect(route('login'));
    expect(savedFiles())->toBeEmpty();
});
