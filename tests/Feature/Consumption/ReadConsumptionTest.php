<?php

declare(strict_types=1);

use App\Actions\Consumption\SaveConsumption;
use App\Contracts\DocumentReader;
use App\Data\Reading\ConsumptionReading;
use App\Enums\ConsumptionOutcome;
use App\Enums\ConsumptionSource;
use App\Enums\ReadingPurpose;
use App\Enums\Role;
use App\Enums\UtilityType;
use App\Exceptions\Reading\ReadingFailed;
use App\Jobs\ReadConsumption;
use App\Models\AiReading;
use App\Models\Document;
use App\Models\Family;
use App\Models\File;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-21 10:00', 'Europe/Rome'));
    Http::preventStrayRequests();
    app()->instance(DocumentReader::class, $this->reader = new FakeDocumentReader);
    $this->family = Family::factory()->create();
    $this->user = User::factory()->for($this->family)->create(['role' => Role::Member]);
    $familyId = $this->family->id;

    CurrentFamily::using($familyId, function () use ($familyId): void {
        $this->utility = Utility::factory()->create(['family_id' => $familyId, 'type' => UtilityType::Gas]);
        $file = File::factory()->create(['family_id' => $familyId, 'mime_type' => 'application/pdf']);
        Storage::disk('local')->put($file->path, '%PDF-1.4 bolletta gas');
        $this->document = Document::factory()->create(['family_id' => $familyId, 'file_id' => $file->id, 'title' => 'Bolletta gas', 'date' => '2026-10-05']);
        $this->utility->documents()->attach($this->document->id);
    });
});

afterEach(fn () => CurrentFamily::clear());

/**
 * Runs the job of a bill as the worker of the queue does (also with a faked queue), by default the bill of the test.
 *
 * @param  Document|null  $document  the bill, null for the one of the test
 */
function readConsumptionRun(?Document $document = null): void
{
    app()->call([new ReadConsumption(test()->family->id, test()->utility->id, ($document ?? test()->document)->id, test()->user->id, 'it'), 'handle']);
}

/**
 * What the model finds on the bill of the test.
 *
 * @param  array<string, mixed>  $changes  values that differ from a good gas bill
 */
function readConsumptionFinds(array $changes = []): void
{
    test()->reader->readsConsumption(test()->document->file_id, new ConsumptionReading(...[
        'isBill' => true,
        'periodStart' => '2026-08-01',
        'periodEnd' => '2026-09-30',
        'quantity' => 120.0,
        'unit' => 'smc',
        'totalAmount' => 96.4,
        'estimated' => true,
        'adjusted' => false,
        ...$changes,
    ]));
}

/**
 * The consumption readings of the family.
 *
 * @return list<AiReading>
 */
function readConsumptionCalls(): array
{
    return CurrentFamily::using(test()->family->id, static fn (): array => AiReading::query()->where('purpose', ReadingPurpose::Consumption)->get()->all());
}

/** The consumption row of the bill of the test, if any. */
function readConsumptionRow(): ?UtilityConsumption
{
    return CurrentFamily::using(test()->family->id, static fn (): ?UtilityConsumption => UtilityConsumption::query()->where('document_id', test()->document->id)->first());
}

it('saves the consumption read from a bill, and the call says so', function (): void {
    readConsumptionFinds();

    readConsumptionRun();

    $row = readConsumptionRow();
    $calls = readConsumptionCalls();
    expect($row?->source)->toBe(ConsumptionSource::Reading)
        ->and($row?->utility_id)->toBe($this->utility->id)
        ->and((float) $row?->quantity)->toBe(120.0)
        ->and((float) $row?->amount)->toBe(96.4)
        ->and($row?->is_estimated)->toBeTrue()
        ->and($row?->period_start->toDateString())->toBe('2026-08-01')
        ->and($calls)->toHaveCount(1)
        ->and($calls[0]->outcome)->toBe(ConsumptionOutcome::Saved)
        ->and($calls[0]->file_id)->toBe($this->document->file_id)
        ->and($calls[0]->user_id)->toBe($this->user->id);
});

it('marks a paper that is not a bill and saves nothing', function (): void {
    $this->reader->readsConsumption($this->document->file_id, new ConsumptionReading(isBill: false));

    readConsumptionRun();

    expect(readConsumptionRow())->toBeNull()
        ->and(readConsumptionCalls()[0]->outcome)->toBe(ConsumptionOutcome::NotABill);
});

it('marks kWh on a gas bill as the wrong unit, and the bill is to check on the page', function (): void {
    readConsumptionFinds(['unit' => 'kwh', 'quantity' => 312.0]);

    readConsumptionRun();

    expect(readConsumptionRow())->toBeNull()
        ->and(readConsumptionCalls()[0]->outcome)->toBe(ConsumptionOutcome::WrongUnit);

    $this->actingAs($this->user)->get(route('budget.consumption', ['utility' => $this->utility->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('consumption.toCheck.0.document_id', $this->document->id)
            ->where('consumption.toCheck.0.reason', 'Wrong unit')
            ->where('consumption.archive', null));
});

it('does not call the reader while the reading is off or the readings are used up', function (Closure $setUp): void {
    readConsumptionFinds();
    $setUp();

    readConsumptionRun();

    expect($this->reader->consumptionReads())->toBe(0)
        ->and(readConsumptionCalls())->toBe([])
        ->and(readConsumptionRow())->toBeNull();
})->with([
    'off' => [fn () => test()->reader->off()],
    'used up' => [fn () => test()->family->forceFill(['reading_limit' => 0])->save()],
]);

it('does not read again a bill with its consumption or already read', function (Closure $setUp): void {
    readConsumptionFinds();
    $familyId = $this->family->id;
    CurrentFamily::using($familyId, $setUp);

    readConsumptionRun();

    expect($this->reader->consumptionReads())->toBe(0);
})->with([
    'with its consumption' => [fn () => UtilityConsumption::factory()->create(['family_id' => test()->family->id, 'utility_id' => test()->utility->id, 'document_id' => test()->document->id, 'source' => ConsumptionSource::Manual])],
    'already read' => [fn () => AiReading::factory()->create(['family_id' => test()->family->id, 'file_id' => test()->document->file_id, 'purpose' => ReadingPurpose::Consumption])],
]);

it('does not try again an answer that was paid but cannot be used, and leaves the bill to check', function (): void {
    $this->reader->fails(ReadingFailed::invalidAnswer());

    readConsumptionRun();

    $calls = readConsumptionCalls();
    expect($calls)->toHaveCount(1)
        ->and($calls[0]->outcome)->toBe(ConsumptionOutcome::NoConsumption)
        ->and(readConsumptionRow())->toBeNull();
});

it('does not throw an error after the call, so the queue does not pay it twice', function (): void {
    Exceptions::fake();
    readConsumptionFinds();
    app()->bind(SaveConsumption::class, static fn (): never => throw new RuntimeException('the database went away'));

    readConsumptionRun();

    Exceptions::assertReported(RuntimeException::class);
    expect(readConsumptionCalls())->toHaveCount(1)
        ->and(readConsumptionRow())->toBeNull();
});

it('leaves the bill to check when an error after the call stops the saving', function (): void {
    Exceptions::fake();
    readConsumptionFinds();
    app()->bind(SaveConsumption::class, static fn (): never => throw new RuntimeException('the database went away'));

    readConsumptionRun();

    expect(readConsumptionCalls()[0]->outcome)->toBe(ConsumptionOutcome::NoConsumption);
});

it('gives an outcome to a call left without one, without calling the reader again', function (): void {
    readConsumptionFinds();
    $familyId = $this->family->id;
    $fileId = $this->document->file_id;
    CurrentFamily::using($familyId, static fn (): AiReading => AiReading::factory()->create(['family_id' => $familyId, 'file_id' => $fileId, 'purpose' => ReadingPurpose::Consumption, 'outcome' => null]));

    readConsumptionRun();

    $calls = readConsumptionCalls();
    expect($this->reader->consumptionReads())->toBe(0)
        ->and($calls)->toHaveCount(1)
        ->and($calls[0]->outcome)->toBe(ConsumptionOutcome::NoConsumption);
});

it('leaves alone the outcome of a call that has one', function (): void {
    readConsumptionFinds();
    $familyId = $this->family->id;
    $fileId = $this->document->file_id;
    CurrentFamily::using($familyId, static fn (): AiReading => AiReading::factory()->create(['family_id' => $familyId, 'file_id' => $fileId, 'purpose' => ReadingPurpose::Consumption, 'outcome' => ConsumptionOutcome::NotABill]));

    readConsumptionRun();

    expect(readConsumptionCalls()[0]->outcome)->toBe(ConsumptionOutcome::NotABill);
});

it('records the call as the account that asked, without signing anyone in', function (): void {
    readConsumptionFinds();

    readConsumptionRun();

    expect(readConsumptionCalls()[0]->user_id)->toBe($this->user->id)
        ->and(Auth::guard('web')->hasUser())->toBeFalse();
});

it('shows on the page that the bills are being read, and how many are done', function (): void {
    $second = CurrentFamily::using($this->family->id, function (): Document {
        $document = Document::factory()->create(['family_id' => $this->family->id, 'date' => '2026-08-05']);
        Storage::disk('local')->put($document->file->path, '%PDF-1.4 lettera');
        $this->utility->documents()->attach($document->id);

        return $document;
    });
    Queue::fake();

    $this->actingAs($this->user)->post(route('budget.consumption.read', $this->utility))->assertRedirect();
    $page = route('budget.consumption', ['utility' => $this->utility->id]);

    $this->get($page)->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('consumption.archive.running', true)
        ->where('consumption.archive.done', 0)
        ->where('consumption.archive.total', 2));

    readConsumptionFinds();
    readConsumptionRun();

    $this->get($page)->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('consumption.archive.running', true)
        ->where('consumption.archive.done', 1)
        ->where('consumption.archive.total', 2));

    // a letter, not a bill: read, so no longer in the archive
    readConsumptionRun($second);

    $this->get($page)->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('consumption.archive', null));
});

it('lets a member start the reading, and answers how many bills it reads', function (): void {
    Queue::fake();
    $page = route('budget.consumption', ['utility' => $this->utility->id]);

    $this->actingAs($this->user)->from($page)
        ->post(route('budget.consumption.read', $this->utility))
        ->assertRedirect($page)
        ->assertInertiaFlash('toast.message', 'I am reading 1 bill.');

    Queue::assertPushed(ReadConsumption::class, 1);
});

it('answers 404 for a utility of another family or without consumption', function (): void {
    $other = Family::factory()->create();
    $elsewhere = CurrentFamily::using($other->id, static fn (): Utility => Utility::factory()->create(['family_id' => $other->id, 'type' => UtilityType::Gas]));
    $internet = CurrentFamily::using($this->family->id, fn (): Utility => Utility::factory()->create(['family_id' => $this->family->id, 'type' => UtilityType::Internet]));

    $this->actingAs($this->user)->post(route('budget.consumption.read', $elsewhere))->assertNotFound();
    $this->actingAs($this->user)->post(route('budget.consumption.read', $internet))->assertNotFound();
});

it('refuses while the reading is off or the readings are used up', function (Closure $setUp): void {
    Queue::fake();
    $setUp();

    $this->actingAs($this->user)->post(route('budget.consumption.read', $this->utility))
        ->assertSessionHasErrors('read');

    Queue::assertNothingPushed();
})->with([
    'off' => [fn () => test()->reader->off()],
    'used up' => [fn () => test()->family->forceFill(['reading_limit' => 0])->save()],
]);

it('reads a shared bill for the gas when only the electricity has its row', function (): void {
    $familyId = $this->family->id;
    CurrentFamily::using($familyId, function () use ($familyId): void {
        $electricity = Utility::factory()->create(['family_id' => $familyId, 'type' => UtilityType::Electricity]);
        $electricity->documents()->attach($this->document->id);
        UtilityConsumption::factory()->create(['family_id' => $familyId, 'utility_id' => $electricity->id, 'document_id' => $this->document->id, 'source' => ConsumptionSource::Reading, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'quantity' => 201]);
    });
    readConsumptionFinds();

    readConsumptionRun();

    $gas = CurrentFamily::using($familyId, fn (): ?UtilityConsumption => UtilityConsumption::query()->where('document_id', $this->document->id)->where('utility_id', $this->utility->id)->first());
    expect($gas?->quantity)->not->toBeNull()
        ->and((float) $gas?->quantity)->toBe(120.0)
        ->and(readConsumptionCalls())->toHaveCount(1)
        ->and(readConsumptionCalls()[0]->outcome)->toBe(ConsumptionOutcome::Saved);
});
