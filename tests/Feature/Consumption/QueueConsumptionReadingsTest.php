<?php

declare(strict_types=1);

use App\Actions\Consumption\QueueConsumptionReadings;
use App\Enums\ReadingPurpose;
use App\Enums\Role;
use App\Enums\UtilityType;
use App\Jobs\ReadConsumption;
use App\Models\AiReading;
use App\Models\Document;
use App\Models\Family;
use App\Models\File;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Services\Reading\ArchiveReadingRun;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-21 10:00', 'Europe/Rome'));
    Queue::fake();
    $this->family = Family::factory()->create(['reading_limit' => 5]);
    $this->user = User::factory()->for($this->family)->create(['role' => Role::Member]);
    $this->utility = CurrentFamily::using($this->family->id, fn (): Utility => Utility::factory()->create(['family_id' => $this->family->id, 'type' => UtilityType::Gas]));
});

afterEach(fn () => CurrentFamily::clear());

/**
 * A bill of the gas utility of the test, dated, with a file of the given type.
 *
 * @param  string  $date  its date, `YYYY-MM-DD`
 * @param  string  $mime  the type of its file
 */
function queueConsumptionBill(string $date, string $mime = 'application/pdf'): Document
{
    $familyId = test()->family->id;

    return CurrentFamily::using($familyId, static function () use ($date, $mime, $familyId): Document {
        $file = File::factory()->create(['family_id' => $familyId, 'mime_type' => $mime]);
        $document = Document::factory()->create(['family_id' => $familyId, 'file_id' => $file->id, 'date' => $date]);
        test()->utility->documents()->attach($document->id);

        return $document;
    });
}

/**
 * The documents of the queued readings, in the order they were queued.
 *
 * @return list<int>
 */
function queueConsumptionQueued(): array
{
    return Queue::pushed(ReadConsumption::class)->map(static fn (ReadConsumption $job): int => $job->documentId)->values()->all();
}

it('queues the most recent bills that fit in the readings left', function (): void {
    $bills = collect(range(1, 16))->map(static fn (int $month): Document => queueConsumptionBill(CarbonImmutable::parse('2025-01-10')->addMonths($month)->toDateString()));

    $count = CurrentFamily::using($this->family->id, fn (): int => resolve(QueueConsumptionReadings::class)->handle($this->utility, $this->user, 'it'));

    expect($count)->toBe(5)
        ->and(queueConsumptionQueued())->toBe($bills->reverse()->take(5)->pluck('id')->values()->all());

    $job = Queue::pushed(ReadConsumption::class)->first();
    expect($job->familyId)->toBe($this->family->id)
        ->and($job->utilityId)->toBe($this->utility->id)
        ->and($job->userId)->toBe($this->user->id)
        ->and($job->locale)->toBe('it');
});

it('does not queue a bill with its consumption, one already read, one without a file it can read', function (): void {
    $toRead = queueConsumptionBill('2026-01-10');
    $withRow = queueConsumptionBill('2026-02-10');
    $tried = queueConsumptionBill('2026-03-10');
    queueConsumptionBill('2026-04-10', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $photo = queueConsumptionBill('2026-05-10', 'image/jpeg');
    $familyId = $this->family->id;
    CurrentFamily::using($familyId, function () use ($withRow, $tried, $familyId): void {
        UtilityConsumption::factory()->create(['family_id' => $familyId, 'utility_id' => $this->utility->id, 'document_id' => $withRow->id]);
        AiReading::factory()->create(['family_id' => $familyId, 'file_id' => $tried->file_id, 'purpose' => ReadingPurpose::Consumption, 'outcome' => null]);
    });

    $count = CurrentFamily::using($familyId, fn (): int => resolve(QueueConsumptionReadings::class)->handle($this->utility, $this->user, 'it'));

    expect($count)->toBe(2)
        ->and(queueConsumptionQueued())->toBe([$photo->id, $toRead->id]);
});

it('queues nothing when the readings of the month are used up', function (): void {
    queueConsumptionBill('2026-01-10');
    $this->family->forceFill(['reading_limit' => 0])->save();

    $count = CurrentFamily::using($this->family->id, fn (): int => resolve(QueueConsumptionReadings::class)->handle($this->utility, $this->user, 'it'));

    expect($count)->toBe(0);
    Queue::assertNothingPushed();
});

it('keeps the run of the reading inside the family', function (): void {
    queueConsumptionBill('2026-01-10');
    $other = Family::factory()->create();

    CurrentFamily::using($this->family->id, fn (): int => resolve(QueueConsumptionReadings::class)->handle($this->utility, $this->user, 'it'));

    expect(CurrentFamily::using($this->family->id, fn (): ?array => resolve(ArchiveReadingRun::class)->progress($this->utility->id)))->toBe(['done' => 0, 'total' => 1])
        ->and(CurrentFamily::using($other->id, fn (): ?array => resolve(ArchiveReadingRun::class)->progress($this->utility->id)))->toBeNull();
});
