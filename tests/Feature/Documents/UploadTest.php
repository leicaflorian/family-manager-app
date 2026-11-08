<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Data\PendingFileData;
use App\Enums\FileSource;
use App\Enums\ReadingStatus;
use App\Enums\Role;
use App\Jobs\ReadFile;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\File;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    Storage::fake('local');
    $this->marco = registerFamily();
    $this->actingAs($this->marco);
    $family = $this->marco->family;

    $this->laura = Person::factory()->for($family)->create(['first_name' => 'Laura', 'last_name' => 'Bianchi']);
    $this->comune = Organization::factory()->for($family)->create(['name' => 'Comune di Bologna']);
    $this->employment = Employment::factory()->for($family)->create(['person_id' => $this->laura->id, 'organization_id' => $this->comune->id]);
    $this->payslips = DocumentCategory::query()->where('name', 'Payslips')->sole();
});

/**
 * Uploads one file, checks that *Import* opens with it, and returns the row the upload created.
 */
function uploadOne(string $name, int $kilobytes = 120): File
{
    $response = test()->post(route('upload.store'), ['files' => [UploadedFile::fake()->create($name, $kilobytes, 'application/pdf')]])
        ->assertSessionHasNoErrors();
    $file = File::query()->latest('id')->firstOrFail();
    $response->assertRedirect(route('import', ['files' => (string) $file->id]));

    return $file;
}

/**
 * The files waiting for their document, as the pages receive them.
 *
 * @return list<array<string, mixed>>
 */
function waitingData(): array
{
    return array_values(array_map(static fn (PendingFileData $file): array => $file->toArray(), PendingFileData::waiting()));
}

/**
 * Files the document of a waiting file through *Import*, as the page sends it.
 *
 * @param  array<string, mixed>  $document
 * @param  array<string, string>  $query
 */
function importOne(File $file, array $document, array $query = []): TestResponse
{
    return test()->post(route('import.store', $query), ['files' => [['file_id' => $file->id, 'document' => ['status' => 'active', ...$document]]]]);
}

test('an uploaded file waits with the title taken from its name', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');

    Storage::disk('local')->assertExists($file->path);
    $this->get(route('import', ['files' => (string) $file->id]))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('documents/Import')
        ->has('files', 1)
        ->where('files.0.id', $file->id)
        ->where('files.0.name', 'Busta paga novembre 2026.pdf')
        ->where('files.0.title', 'Busta paga novembre 2026')
        ->has('links', 1)
        ->where('links.0.type', 'employment')
        ->where('links.0.options.0.value', (string) $this->employment->id)
        ->where('links.0.options.0.label', 'Laura - Comune di Bologna'));
});

test('saved with the employment of Laura, the document is among its documents', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');

    importOne($file, [
        'title' => 'Busta paga novembre 2026',
        'category_id' => $this->payslips->id,
        'person_id' => $this->laura->id,
        'organization_id' => $this->comune->id,
        'date' => '2026-11-27',
        'links' => [['type' => 'employment', 'id' => $this->employment->id]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

    $document = Document::query()->where('title', 'Busta paga novembre 2026')->sole();
    expect($document)->file_id->toBe($file->id)->category_id->toBe($this->payslips->id)->person_id->toBe($this->laura->id)
        ->and($this->employment->documents()->pluck('documents.id')->all())->toBe([$document->id]);
    expect(waitingData())->toBe([]);
});

test('Import opens with the files just uploaded, not with those that were already waiting', function (): void {
    uploadOne('Busta paga novembre 2026.pdf');

    $response = $this->post(route('upload.store'), ['files' => [
        UploadedFile::fake()->create('Busta paga dicembre 2026.pdf', 120, 'application/pdf'),
        UploadedFile::fake()->create('Busta paga gennaio 2027.pdf', 120, 'application/pdf'),
    ]])->assertSessionHasNoErrors();

    $new = File::query()->whereIn('original_name', ['Busta paga dicembre 2026.pdf', 'Busta paga gennaio 2027.pdf'])->oldest('id')->pluck('id');
    $response->assertRedirect(route('import', ['files' => $new->implode(',')]));
});

test('files dropped in To sort stay there, waiting, instead of opening Import', function (): void {
    Queue::fake();

    $this->post(route('upload.store', ['stay' => 1]), ['files' => [UploadedFile::fake()->create('Avviso TARI 2026.pdf', 120, 'application/pdf')]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('to-sort'));

    $file = File::query()->sole();
    expect($file->to_sort)->toBeTrue()
        ->and($file->source)->toBe(FileSource::Upload);
});

test('Upload only takes the files: the waiting ones are in To sort', function (): void {
    uploadOne('Busta paga novembre 2026.pdf');

    $this->get(route('upload'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('documents/Upload')
        ->missing('pending')
        ->where('to_sort_count', 1));
});

test('an organization of another family is a validation error', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): Organization => Organization::factory()->for($rossi->family)->create(['name' => 'Comune di Roma']));

    importOne($file, ['title' => 'Busta paga', 'organization_id' => $theirs->id])
        ->assertSessionHasErrors('files.0.document.organization_id');

    expect(Document::query()->count())->toBe(0);
});

test('a link to a record of another family, or of a type without documents, is a validation error', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');
    $rossi = registerFamily('Rossi', 'anna@example.com');
    $theirs = CurrentFamily::using($rossi->family_id, fn (): Employment => Employment::factory()->for($rossi->family)->create());

    importOne($file, [
        'title' => 'Busta paga',
        'links' => [['type' => 'employment', 'id' => $theirs->id], ['type' => 'person', 'id' => $this->laura->id]],
    ])->assertSessionHasErrors(['files.0.document.links.0.id', 'files.0.document.links.1.type']);
});

test('a file of 26 MB is rejected with a message in Italian', function (): void {
    $this->withHeader('Accept-Language', 'it')
        ->post(route('upload.store'), ['files' => [UploadedFile::fake()->create('Scansione.pdf', 26 * 1024, 'application/pdf')]])
        ->assertSessionHasErrors(['files.0' => 'Ogni file può essere al massimo di 25 MB.']);

    expect(File::query()->count())->toBe(0);
});

test('a waiting file that is discarded leaves the disk too', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');

    $this->delete(route('upload.destroy', $file))->assertRedirect(route('to-sort'));

    expect($file->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($file->path);
});

test('a file that has its document is not discarded from To sort', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');
    importOne($file, ['title' => 'Busta paga'])->assertSessionHasNoErrors();

    $this->delete(route('upload.destroy', $file))->assertNotFound();

    expect($file->fresh())->not->toBeNull();
});

test('a waiting file has a preview address only when it has a preview', function (): void {
    $file = uploadOne('Busta paga novembre 2026.pdf');
    expect(waitingData()[0]['preview_url'])->toBe(route('files.preview', $file));

    $file->forceFill(['preview_failed' => true])->save();
    expect(waitingData()[0]['preview_url'])->toBeNull();

    $file->forceFill(['preview_failed' => false, 'mime_type' => 'image/png'])->save();
    expect(waitingData()[0]['preview_url'])->toBeNull();
});

test('upload keeps the card it comes from, for the files it sends', function (): void {
    $panda = Vehicle::factory()->for($this->marco->family)->create(['name' => 'Fiat Panda']);

    $this->get(route('upload', ['link' => 'vehicle:'.$panda->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('presetLinks', [['type' => 'vehicle', 'id' => $panda->id]]));
});

test('upload ignores a link of another family or of an unknown type', function (string $link): void {
    $rossi = registerFamily('Rossi', 'mario@rossi.example');
    $other = Vehicle::factory()->for($rossi->family)->create();
    $link = str_replace('{other}', (string) $other->id, $link);

    $this->actingAs($this->marco)
        ->get(route('upload', ['link' => $link]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('presetLinks', []));
})->with(['spaceship:1', 'vehicle:999999', 'vehicle:abc', 'nothing', 'vehicle:{other}']);

test('files uploaded from a card open Import with the card, linked to it', function (): void {
    $panda = Vehicle::factory()->for($this->marco->family)->create(['name' => 'Fiat Panda']);
    $link = 'vehicle:'.$panda->id;
    $files = ['files' => [UploadedFile::fake()->create('Libretto.pdf', 120, 'application/pdf'), UploadedFile::fake()->create('Bollo.pdf', 120, 'application/pdf')]];

    $response = $this->post(route('upload.store', ['link' => $link]), $files);

    $ids = File::query()->oldest('id')->pluck('id')->implode(',');
    $response->assertRedirect(route('import', ['files' => $ids, 'link' => $link]));
    $this->get(route('import', ['files' => $ids, 'link' => $link]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('files', 2)
            ->where('files.0.links', [['type' => 'vehicle', 'id' => $panda->id]])
            ->where('presetLinks', [['type' => 'vehicle', 'id' => $panda->id]]));
});

test('a link of another family is not carried through the upload', function (): void {
    $rossi = registerFamily('Rossi', 'mario@rossi.example');
    $other = Vehicle::factory()->for($rossi->family)->create();

    $response = $this->actingAs($this->marco)
        ->post(route('upload.store', ['link' => 'vehicle:'.$other->id]), ['files' => [UploadedFile::fake()->create('Libretto.pdf', 120, 'application/pdf')]]);

    $response->assertRedirect(route('import', ['files' => (string) File::query()->sole()->id]));
});

test('a file uploaded from a card keeps the card when Import opens without it', function (): void {
    $panda = Vehicle::factory()->for($this->marco->family)->create(['name' => 'Fiat Panda']);
    $this->post(route('upload.store', ['link' => 'vehicle:'.$panda->id]), ['files' => [UploadedFile::fake()->create('Libretto.pdf', 120, 'application/pdf')]]);

    expect(waitingData()[0]['links'])->toBe([['type' => 'vehicle', 'id' => $panda->id]]);
});

test('a file uploaded without a card is linked to no card', function (): void {
    uploadOne('Busta paga novembre 2026.pdf');

    expect(waitingData()[0]['links'])->toBe([]);
});

test('the card of a waiting file that is deleted is no longer proposed', function (): void {
    $panda = Vehicle::factory()->for($this->marco->family)->create(['name' => 'Fiat Panda']);
    $this->post(route('upload.store', ['link' => 'vehicle:'.$panda->id]), ['files' => [UploadedFile::fake()->create('Libretto.pdf', 120, 'application/pdf')]]);
    $panda->delete();

    expect(waitingData()[0]['links'])->toBe([]);
});

test('a filed file forgets the card it was uploaded from', function (): void {
    $panda = Vehicle::factory()->for($this->marco->family)->create(['name' => 'Fiat Panda']);
    $this->post(route('upload.store', ['link' => 'vehicle:'.$panda->id]), ['files' => [UploadedFile::fake()->create('Libretto.pdf', 120, 'application/pdf')]]);
    $file = File::query()->sole();

    importOne($file, ['title' => 'Libretto'])->assertSessionHasNoErrors();

    expect($file->fresh())->link_type->toBeNull()->link_id->toBeNull();
});

/**
 * Uploads one file with the given content and returns the row the upload created.
 */
function uploadContent(string $name, string $content): File
{
    $response = test()->post(route('upload.store'), ['files' => [UploadedFile::fake()->createWithContent($name, $content)]])
        ->assertSessionHasNoErrors();
    $file = File::query()->latest('id')->firstOrFail();
    $response->assertRedirect(route('import', ['files' => (string) $file->id]));

    return $file;
}

test('uploading reads the file by itself', function (): void {
    Queue::fake();
    app()->instance(DocumentReader::class, new FakeDocumentReader);

    $this->post(route('upload.store'), ['files' => [
        UploadedFile::fake()->createWithContent('Bolletta Edison.pdf', 'the bill of october'),
        UploadedFile::fake()->createWithContent('Libretto Panda.pdf', 'the booklet of the car'),
    ]])->assertSessionHasNoErrors()->assertRedirect(route('import', ['files' => File::query()->oldest('id')->pluck('id')->implode(',')]));

    Queue::assertPushed(ReadFile::class, 2);
    Queue::assertPushed(ReadFile::class, fn (ReadFile $job): bool => $job->userId === $this->marco->id && $job->locale === 'en');
    expect(File::query()->oldest('id')->pluck('reading_status')->all())->toBe([ReadingStatus::Queued, ReadingStatus::Queued]);
    expect(waitingData()[0])->reading_status->toBe('queued')->reading_error->toBeNull()->proposal->toBeNull();
});

test('a stored proposal that cannot be read is no proposal', function (): void {
    Exceptions::fake();
    $file = uploadOne('Bolletta Edison.pdf');
    // a proposal written by an older version of the app, without the headline it needs now
    $file->forceFill(['reading_status' => ReadingStatus::Done, 'reading' => ['proposal' => ['title' => 'Bolletta']]])->save();

    $this->get(route('import', ['files' => (string) $file->id]))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('files.0.reading_status', 'done')
        ->where('files.0.proposal', null));
    Exceptions::assertReported(CannotCreateData::class);
});

test('the checksum and the note are written', function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-22 10:15', 'Europe/Rome'));

    $file = uploadContent('Bolletta Edison.pdf', 'the bill of october');

    expect($file)
        ->checksum->toBe(hash('sha256', 'the bill of october'))
        ->source->toBe(FileSource::Upload)
        ->note->toBe('Uploaded manually on 22/10/2026 by Marco');
    expect(waitingData()[0])->source->toBe('upload')->note->toBe('Uploaded manually on 22/10/2026 by Marco')->copy_of->toBeNull();
});

test('the note follows the language of the request', function (string $language, string $note): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-22 10:15', 'Europe/Rome'));

    $this->withHeader('Accept-Language', $language);
    $file = uploadContent('Bolletta Edison.pdf', 'the bill of october');

    expect($file->note)->toBe($note);
})->with([
    'Italian' => ['it-IT,it;q=0.9', 'Caricato manualmente il 22/10/2026 da Marco'],
    'English' => ['en-GB,en;q=0.9', 'Uploaded manually on 22/10/2026 by Marco'],
]);

test('a copy is marked', function (): void {
    $bill = uploadContent('Telefono 2026-10-25 19.04.pdf', 'the bill of october');
    uploadContent('Libretto Panda.pdf', 'the booklet of the car');
    $copy = uploadContent('Telefono 2026-10-25 19.06.pdf', 'the bill of october');

    expect($copy->copyOf()?->id)->toBe($bill->id)->and($bill->copyOf())->toBeNull();
    expect(array_column(waitingData(), 'copy_of'))->toBe([null, null, 'Telefono 2026-10-25 19.04.pdf']);
});

test('a copy of the file of a document is marked', function (): void {
    $bill = uploadContent('Bolletta Edison.pdf', 'the bill of october');
    importOne($bill, ['title' => 'Bolletta Edison'])->assertSessionHasNoErrors();

    $copy = uploadContent('Bolletta di nuovo.pdf', 'the bill of october');

    expect($copy->copyOf()?->id)->toBe($bill->id);
    expect(waitingData()[0]['copy_of'])->toBe('Bolletta Edison.pdf');
});

test('a file the same as one that is neither waiting nor a document is no copy', function (): void {
    // A photo of a person: a file of the family that is not waiting and has no document
    File::factory()->for($this->marco->family)->create(['checksum' => hash('sha256', 'the photo of Laura'), 'to_sort' => false]);

    $file = uploadContent('Laura.pdf', 'the photo of Laura');

    expect($file->copyOf())->toBeNull();
});

test('to_sort_count leaves out the copies', function (): void {
    $this->get(route('upload'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('to_sort_count', 0));

    uploadContent('Telefono 2026-10-25 19.04.pdf', 'the bill of october');
    uploadContent('Libretto Panda.pdf', 'the booklet of the car');
    uploadContent('Telefono 2026-10-25 19.06.pdf', 'the bill of october');

    $this->get(route('upload'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('to_sort_count', 2));
});

test('to_sort_count counts only the waiting files of the family', function (): void {
    uploadContent('Libretto Panda.pdf', 'the booklet of the car');
    $rossi = registerFamily('Rossi', 'anna@example.com');
    CurrentFamily::using($rossi->family_id, fn (): File => File::factory()->for($rossi->family)->create(['to_sort' => true]));

    $this->actingAs($this->marco)->get(route('upload'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('to_sort_count', 1));
});

test('the same file in another family is no copy and is counted', function (): void {
    $rossi = registerFamily('Rossi', 'anna@example.com');
    // the older file, with the same content, waits in the Rossi family
    $theirs = CurrentFamily::using($rossi->family_id, fn (): File => File::factory()->for($rossi->family)->create([
        'to_sort' => true, 'checksum' => hash('sha256', 'the bill of october'),
    ]));

    $this->actingAs($this->marco);
    $ours = uploadContent('Bolletta Edison.pdf', 'the bill of october');

    expect($ours->copyOf())->toBeNull()->and($ours->id)->toBeGreaterThan($theirs->id);
    expect(waitingData()[0]['copy_of'])->toBeNull();
    $this->get(route('upload'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('to_sort_count', 1));
    $this->actingAs($rossi)->get(route('upload'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('to_sort_count', 1));
});

test('the copies of the waiting files are found without a query per file', function (): void {
    $rosa = User::factory()->for($this->marco->family)->create(['role' => Role::Member]);
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        PendingFileData::waiting();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    uploadContent('Telefono 2026-10-25 19.04.pdf', 'the bill of october');
    $this->actingAs($rosa);
    $one = $queries();

    $this->actingAs($this->marco);
    uploadContent('Libretto Panda.pdf', 'the booklet of the car');
    uploadContent('Telefono 2026-10-25 19.06.pdf', 'the bill of october');
    $this->actingAs($rosa);

    expect($queries())->toBe($one);
});
