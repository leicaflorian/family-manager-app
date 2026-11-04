<?php

declare(strict_types=1);

use App\Actions\Reading\QueueReading;
use App\Ai\Agents\DocumentReaderAgent;
use App\Contracts\DocumentReader;
use App\Data\Reading\Extraction;
use App\Enums\ReadingPurpose;
use App\Enums\ReadingStatus;
use App\Exceptions\Reading\ReadingFailed;
use App\Jobs\PreparePreview;
use App\Jobs\ReadFile;
use App\Models\AiReading;
use App\Models\Family;
use App\Models\File;
use App\Models\Organization;
use App\Models\User;
use App\Services\Reading\DocumentProposal;
use App\Services\Reading\ReadingContext;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\Timeout;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    Storage::fake('local');
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    // The jobs wait in the `jobs` table, as online, and a worker runs them one at a time
    config(['queue.default' => 'database']);
    $this->seed(BianchiFamilySeeder::class);
    $this->bianchi = Family::query()->where('name', 'Bianchi')->sole();
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->extraction = Extraction::from(['kind' => 'utility_bill', 'confidence' => 'high', 'issuer_name' => 'Edison Energia S.p.A.', 'total_amount' => 84.2]);
    app()->instance(DocumentReader::class, $this->reader = (new FakeDocumentReader)->returns($this->extraction));
    $this->file = fileToRead($this->bianchi);
});

/**
 * A file of a family on the private disk, with the bytes of a fixture or the given ones.
 *
 * @param  Family  $family  the family of the file
 * @param  string|null  $bytes  the content; null for the PDF of one page
 * @param  string  $mime  the type the row says
 */
function fileToRead(Family $family, ?string $bytes = null, string $mime = 'application/pdf'): File
{
    $bytes ??= (string) file_get_contents(base_path('tests/Fixtures/one-page.pdf'));
    $file = File::factory()->for($family)->create([
        'path' => "families/{$family->id}/".Str::uuid().'.pdf', 'mime_type' => $mime, 'size' => strlen($bytes),
    ]);
    Storage::disk('local')->put($file->path, $bytes);

    return $file;
}

/**
 * Asks for the reading of a file, as *Carica* does.
 *
 * @param  File  $file  the file
 * @param  User|null  $user  who uploaded it
 * @param  string  $locale  the language of who uploaded it
 * @param  bool  $again  true for *Leggi di nuovo*
 */
function queueReadingOf(File $file, ?User $user, string $locale = 'it', bool $again = false): ReadingStatus
{
    return resolve(QueueReading::class)->handle($file, $user, $locale, $again);
}

/**
 * Runs the next job of the queue that is due, as the worker online does.
 */
function workQueue(): void
{
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);
}

/**
 * The jobs of a class waiting in the queue.
 *
 * @param  class-string  $job  the class of the job; the readings unless said otherwise
 */
function waitingJobs(string $job = ReadFile::class): int
{
    return DB::table('jobs')->pluck('payload')
        ->filter(fn (string $payload): bool => json_decode($payload, true)['displayName'] === $job)
        ->count();
}

test('a file goes queued, reading, done', function (): void {
    $statuses = [];
    File::updating(function (File $file) use (&$statuses): void {
        if ($file->isDirty('reading_status')) {
            $statuses[] = $file->reading_status;
        }
    });
    Log::spy();

    expect(queueReadingOf($this->file, $this->marco))->toBe(ReadingStatus::Queued)
        ->and(waitingJobs())->toBe(1);

    workQueue();

    $file = $this->file->fresh();
    $proposal = resolve(DocumentProposal::class)->of($this->extraction, ReadingContext::for($file), 'it');
    $reading = AiReading::query()->withoutGlobalScopes()->sole();

    expect($statuses)->toBe([ReadingStatus::Queued, ReadingStatus::Reading, ReadingStatus::Done])
        ->and($file->reading_status)->toBe(ReadingStatus::Done)
        ->and($file->reading_error)->toBeNull()
        ->and($file->reading)->toEqual([
            'extraction' => $this->extraction->toArray(),
            'proposal' => $proposal->toArray(),
            'provider' => 'gemini',
            'model' => 'gemini-3.8-flash',
            'read_at' => '2026-10-25T19:04:00+01:00',
        ])
        ->and($reading->family_id)->toBe($this->bianchi->id)
        ->and($reading->user_id)->toBe($this->marco->id)
        ->and($reading->file_id)->toBe($file->id)
        ->and($reading->purpose)->toBe(ReadingPurpose::Document)
        ->and($reading->provider)->toBe('gemini')
        ->and($reading->model)->toBe('gemini-3.8-flash')
        ->and($reading->input_tokens)->toBe(1200)
        ->and($reading->output_tokens)->toBe(300)
        ->and($reading->cache_read_tokens)->toBe(0)
        ->and($reading->cost_usd)->toBe('0.002025')
        ->and(waitingJobs())->toBe(0);
    Log::shouldHaveReceived('info')
        ->with("[document-reader] file {$file->id} read with gemini/gemini-3.8-flash: utility_bill, high; tokens in 1200, out 300, about \$0.0020")
        ->once();
});

test('a busy service is retried, then failed', function (): void {
    $this->reader->fails(ReadingFailed::busy(new RuntimeException('529 overloaded')));
    queueReadingOf($this->file, $this->marco);

    workQueue();
    expect($this->reader->reads())->toBe(1)
        ->and($this->file->fresh()->reading_status)->toBe(ReadingStatus::Reading);

    // the second try waits 10 seconds, the third 30 more
    $this->travel(9)->seconds();
    workQueue();
    expect($this->reader->reads())->toBe(1);
    $this->travel(1)->seconds();
    workQueue();
    $this->travel(30)->seconds();
    workQueue();

    $file = $this->file->fresh();
    expect($this->reader->reads())->toBe(3)
        ->and($file->reading_status)->toBe(ReadingStatus::Failed)
        ->and($file->reading_error)->toBe('Il servizio AI è occupato, riprova tra poco.')
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(waitingJobs())->toBe(0);
});

test('a refusal fails at once', function (): void {
    $this->reader->fails(ReadingFailed::refused());
    queueReadingOf($this->file, $this->marco);

    workQueue();
    $this->travel(2)->minutes();
    workQueue();

    $file = $this->file->fresh();
    expect($this->reader->reads())->toBe(1)
        ->and($file->reading_status)->toBe(ReadingStatus::Failed)
        ->and($file->reading_error)->toBe(__('The model refused to read the document.', [], 'it'))
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(waitingJobs())->toBe(0);
});

test('one reading per file', function (): void {
    expect(queueReadingOf($this->file, $this->marco))->toBe(ReadingStatus::Queued)
        ->and(queueReadingOf($this->file->fresh(), $this->marco))->toBe(ReadingStatus::Queued);

    // a job sent by another way for the same file does not go in the queue either
    dispatch(new ReadFile($this->file->id, $this->bianchi->id, $this->marco->id, 'it', ReadingPurpose::Document));
    expect(waitingJobs())->toBe(1);

    $this->file->forceFill(['reading_status' => ReadingStatus::Reading])->save();
    expect(queueReadingOf($this->file->fresh(), $this->marco))->toBe(ReadingStatus::Reading);
    $this->file->forceFill(['reading_status' => ReadingStatus::Queued])->save();

    workQueue();
    expect($this->reader->reads())->toBe(1)
        ->and(queueReadingOf($this->file->fresh(), $this->marco))->toBe(ReadingStatus::Done)
        ->and(waitingJobs())->toBe(0);

    // *Leggi di nuovo* reads a file already read
    expect(queueReadingOf($this->file->fresh(), $this->marco, again: true))->toBe(ReadingStatus::Queued)
        ->and(waitingJobs())->toBe(1);
    workQueue(); // the preview of the first reading
    workQueue();
    expect($this->reader->reads())->toBe(2);
});

test('a deleted file ends the job quietly', function (): void {
    queueReadingOf($this->file, $this->marco);
    $this->file->delete();

    workQueue();

    expect($this->reader->reads())->toBe(0)
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(waitingJobs())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('the job works in the family of the file', function (): void {
    $rossi = registerFamily('Rossi', 'mario@example.com');
    $theirs = insideFamilyOf($rossi, fn (): Organization => Organization::factory()->create(['family_id' => $rossi->family_id, 'name' => 'Edison']));
    $ours = insideBianchi(fn (): Organization => Organization::query()->where('name', 'Edison')->sole());
    $this->reader->returns(Extraction::from(['kind' => 'utility_bill', 'confidence' => 'high', 'issuer_name' => 'Edison', 'organization_id' => $theirs->id]));

    // someone of the Rossi is signed in when the worker picks the job
    CurrentFamily::set($rossi->family_id);
    queueReadingOf($this->file, $this->marco);
    workQueue();
    CurrentFamily::clear();

    $file = $this->file->fresh();
    expect($file->reading_status)->toBe(ReadingStatus::Done)
        ->and($file->reading['proposal']['organization_id'])->toBe($ours->id)
        ->and(AiReading::query()->withoutGlobalScopes()->sole()->family_id)->toBe($this->bianchi->id);
});

test('the language of who uploaded', function (): void {
    app()->setLocale('it');
    $refused = fileToRead($this->bianchi);
    queueReadingOf($this->file, $this->marco, 'en');
    workQueue();
    workQueue(); // the preview
    $this->reader->fails(ReadingFailed::refused());
    queueReadingOf($refused, $this->marco, 'en');
    workQueue();

    $context = ReadingContext::for($this->file);
    $english = resolve(DocumentProposal::class)->of($this->extraction, $context, 'en')->headline;
    expect($this->file->fresh()->reading['proposal']['headline'])->toBe($english)
        ->not->toBe(resolve(DocumentProposal::class)->of($this->extraction, $context, 'it')->headline)
        ->and($refused->fresh()->reading_error)->toBe('The model refused to read the document.');
});

test('off without a key', function (): void {
    app()->forgetInstance(DocumentReader::class);
    config(['document_reader.provider' => 'gemini', 'ai.providers.gemini.key' => null]);

    expect(queueReadingOf($this->file, $this->marco))->toBe(ReadingStatus::Off)
        ->and($this->file->fresh()->reading_error)->toBe('La lettura automatica non è attiva.');

    config(['document_reader.provider' => null]);
    $english = fileToRead($this->bianchi);
    expect(queueReadingOf($english, $this->marco, 'en'))->toBe(ReadingStatus::Off)
        ->and($english->fresh()->reading_error)->toBe('Automatic reading is not on.')
        ->and(waitingJobs())->toBe(0);
});

test('a file the model does not read is skipped without a job', function (): void {
    $archive = fileToRead($this->bianchi, 'PK', 'application/zip');

    expect(queueReadingOf($archive, $this->marco))->toBe(ReadingStatus::Skipped)
        ->and($archive->fresh()->reading_error)->toBe('Questo tipo di file non si può leggere.')
        ->and(waitingJobs())->toBe(0);
});

test('the limit of the month', function (): void {
    config(['document_reader.monthly_limit' => 2]);
    AiReading::factory()->for($this->bianchi)->create();

    expect(queueReadingOf($this->file, $this->marco))->toBe(ReadingStatus::Queued);
    workQueue();

    $second = fileToRead($this->bianchi);
    $third = fileToRead($this->bianchi);
    expect(queueReadingOf($second, $this->marco))->toBe(ReadingStatus::Off)
        ->and($second->fresh()->reading_error)->toBe('Hai usato tutte le letture automatiche di ottobre.')
        ->and(queueReadingOf($third, $this->marco, 'en'))->toBe(ReadingStatus::Off)
        ->and($third->fresh()->reading_error)->toBe('You have used all the automatic readings of October.')
        ->and(waitingJobs())->toBe(0);
});

test('the preview is made after the reading', function (): void {
    queueReadingOf($this->file, $this->marco);
    workQueue();

    // the reading is done, and the preview waits for a job of its own
    expect($this->file->fresh()->reading_status)->toBe(ReadingStatus::Done)
        ->and($this->file->fresh()->preview_path)->toBeNull()
        ->and(waitingJobs(PreparePreview::class))->toBe(1);

    workQueue();

    $file = $this->file->fresh();
    expect($file->preview_path)->not->toBeNull()
        ->and(waitingJobs(PreparePreview::class))->toBe(0);
    Storage::disk('local')->assertExists((string) $file->preview_path);
});

test('no preview job after a reading that failed', function (): void {
    $this->reader->fails(ReadingFailed::refused());
    queueReadingOf($this->file, $this->marco);
    workQueue();

    expect(waitingJobs(PreparePreview::class))->toBe(0);
});

test('a failed preview does not fail the reading', function (): void {
    $broken = fileToRead($this->bianchi, (string) file_get_contents(base_path('tests/Fixtures/broken.pdf')));

    queueReadingOf($broken, $this->marco);
    workQueue();
    workQueue();

    $file = $broken->fresh();
    expect($file->reading_status)->toBe(ReadingStatus::Done)
        ->and($file->preview_failed)->toBeTrue()
        ->and($file->preview_path)->toBeNull()
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('the preview of a file deleted meanwhile ends quietly', function (): void {
    queueReadingOf($this->file, $this->marco);
    workQueue();
    $this->file->delete();

    workQueue();

    expect(waitingJobs(PreparePreview::class))->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('a job tried again on a file already read does not read it again', function (): void {
    queueReadingOf($this->file, $this->marco);
    workQueue();

    // as a try killed after the reading was saved, given again to a worker after `retry_after`
    dispatch(new ReadFile($this->file->id, $this->bianchi->id, $this->marco->id, 'it', ReadingPurpose::Document));
    workQueue();

    expect($this->reader->reads())->toBe(1)
        ->and($this->file->fresh()->reading_status)->toBe(ReadingStatus::Done)
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('a reading is counted even when what comes after it fails', function (): void {
    $this->reader->returns(Extraction::from(['kind' => 'utility_bill', 'confidence' => 'high', 'issuer_name' => 'Edison']));
    // the proposal looks the issuer up among the organizations: that search breaks, after the service answered
    Organization::retrieved(function (): void {
        if ($this->reader->reads() > 0) {
            throw new RuntimeException('the proposal broke');
        }
    });

    queueReadingOf($this->file, $this->marco);
    workQueue();

    $reading = AiReading::query()->withoutGlobalScopes()->sole();
    expect($this->reader->reads())->toBe(1)
        ->and($reading->file_id)->toBe($this->file->id)
        ->and($reading->cost_usd)->toBe('0.002025')
        ->and($this->file->fresh()->reading_status)->not->toBe(ReadingStatus::Done);
});

test('a failure after the paid reading fails the file and is not tried again', function (): void {
    Exceptions::fake();
    $this->reader->returns(Extraction::from(['kind' => 'utility_bill', 'confidence' => 'high', 'issuer_name' => 'Edison']));
    // the proposal breaks after the service answered: a try started again would pay a second reading
    Organization::retrieved(function (): void {
        if ($this->reader->reads() > 0) {
            throw new RuntimeException('the proposal broke');
        }
    });

    queueReadingOf($this->file, $this->marco);
    workQueue();
    Date::setTestNow(now()->addMinutes(5));
    workQueue();

    $file = $this->file->fresh();
    expect($this->reader->reads())->toBe(1)
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and($file->reading_status)->toBe(ReadingStatus::Failed)
        ->and($file->reading_error)->toBe('Non è stato possibile leggere il file.')
        ->and(waitingJobs())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'the proposal broke');
});

test('the limit is checked again when the file is read', function (): void {
    config(['document_reader.monthly_limit' => 1]);
    $second = fileToRead($this->bianchi);

    expect(queueReadingOf($this->file, $this->marco))->toBe(ReadingStatus::Queued)
        ->and(queueReadingOf($second, $this->marco))->toBe(ReadingStatus::Queued);
    workQueue();
    workQueue();
    workQueue();

    expect($this->reader->reads())->toBe(1)
        ->and($second->fresh()->reading_status)->toBe(ReadingStatus::Off)
        ->and($second->fresh()->reading_error)->toBe('Hai usato tutte le letture automatiche di ottobre.')
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('a photo that cannot be prepared is skipped by the job', function (): void {
    $photo = fileToRead($this->bianchi, 'not a photo', 'image/jpeg');

    queueReadingOf($photo, $this->marco);
    workQueue();

    $file = $photo->fresh();
    expect($this->reader->reads())->toBe(0)
        ->and($file->reading_status)->toBe(ReadingStatus::Skipped)
        ->and($file->reading_error)->toBe('Questo tipo di file non si può leggere.')
        ->and(AiReading::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('a reading switched off while the file waited does not call the service', function (): void {
    queueReadingOf($this->file, $this->marco);
    $this->reader->off();

    workQueue();

    $file = $this->file->fresh();
    expect($this->reader->reads())->toBe(0)
        ->and($file->reading_status)->toBe(ReadingStatus::Off)
        ->and($file->reading_error)->toBe('La lettura automatica non è attiva.');
});

test('the reading stays when its record cannot be written', function (): void {
    Exceptions::fake();
    AiReading::creating(fn (): never => throw new RuntimeException('the table is not there'));

    queueReadingOf($this->file, $this->marco);
    workQueue();

    expect($this->file->fresh()->reading_status)->toBe(ReadingStatus::Done);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'the table is not there');
});

test('a slow reading ends before the queue gives it to another worker', function (): void {
    queueReadingOf($this->file, $this->marco);
    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);
    $call = new ReflectionClass(DocumentReaderAgent::class)->getAttributes(Timeout::class)[0]->newInstance();

    // a try that outlived `retry_after` would start again on another worker, and be paid twice
    expect($payload['timeout'])->toBe(80)
        ->toBeLessThan(config('queue.connections.database.retry_after'))
        ->toBeGreaterThan($call->value)
        ->and($payload['maxTries'])->toBe(3)
        ->and($payload['backoff'])->toBe('10,30,90');
});
