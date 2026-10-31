<?php

declare(strict_types=1);

use App\Cards\CardDate;
use App\Cards\CardTypes;
use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Enums\DocumentStatus;
use App\Enums\InsurancePolicyStatus;
use App\Enums\IntervalUnit;
use App\Enums\LoanStatus;
use App\Models\Document;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-14 10:00', 'Europe/Rome'));
});

afterEach(fn () => Date::setTestNow());

/**
 * Runs the work inside a fresh family.
 *
 * @param  Closure(): mixed  $work  what to do inside the family
 */
function inNewFamily(Closure $work): mixed
{
    return CurrentFamily::using(Family::factory()->create()->id, $work);
}

/**
 * A card of the kind, with its deadline on the given day and the attributes
 * given; the first active status when the kind has statuses.
 *
 * @param  DeadlineSource  $source  the kind of deadline
 * @param  array<string, mixed>  $attributes  what differs from a card with a deadline
 */
function cardOf(DeadlineSource $source, array $attributes = []): Model
{
    $status = $source->activeStatuses === [] ? [] : ['status' => $source->activeStatuses[0]];

    return $source->model::factory()->create(['family_id' => CurrentFamily::id(), ...$status, $source->column => '2026-12-01', ...$attributes]);
}

/**
 * The ids the kind finds.
 *
 * @param  DeadlineSource  $source  the kind of deadline
 * @return list<int>
 */
function foundBy(DeadlineSource $source): array
{
    return $source->query(CarbonImmutable::today('Europe/Rome'))->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
}

/**
 * The kind of deadline of a field.
 *
 * @param  string  $field  the key, like `vehicles.road_tax_due_date`
 */
function source(string $field): DeadlineSource
{
    $source = DeadlineSources::find($field);
    assert($source instanceof DeadlineSource);

    return $source;
}

describe('the kinds of deadline', function (): void {
    test('every kind of deadline has its test card', function (): void {
        foreach (DeadlineSources::all() as $source) {
            expect(Schema::hasColumn((new $source->model)->getTable(), $source->column))->toBeTrue($source->field)
                ->and($source->field)->toBe((new $source->model)->getTable().'.'.$source->column);
        }
    });

    test('names and fields are unique', function (): void {
        $fields = array_map(fn (DeadlineSource $source): string => $source->field, DeadlineSources::all());
        $labels = array_filter(array_map(fn (DeadlineSource $source): ?string => $source->label, DeadlineSources::all()));

        expect(array_unique($fields))->toHaveCount(11)
            ->and(array_unique($labels))->toHaveCount(10);
    });

    test('the fields are the ones of Directus', function (): void {
        expect(array_map(fn (DeadlineSource $source): string => $source->field, DeadlineSources::all()))->toBe([
            'vehicles.road_tax_due_date', 'vehicles.inspection_due_date', 'insurance_policies.expiry_date',
            'belongings.warranty_expiry_date', 'documents.expiry_date', 'filings.due_date',
            'health_events.next_checkup_date', 'subscriptions.renewal_date', 'subscriptions.trial_end_date',
            'loans.start_date', 'loans.end_date',
        ]);
    });

    test('every expiring card date has its source', function (): void {
        foreach (CardTypes::all() as $type) {
            foreach (array_filter($type->dates(), fn (CardDate $date): bool => $date->expires) as $date) {
                $field = (new ($type->model()))->getTable().'.'.$date->column;

                expect(DeadlineSources::find($field))->not->toBeNull($field);
            }
        }
    });

    test('a vehicle sold has no deadline', function (): void {
        inNewFamily(function (): void {
            $roadTax = source('vehicles.road_tax_due_date');
            $panda = cardOf($roadTax);
            cardOf($roadTax, ['status' => 'sold']);

            expect(foundBy($roadTax))->toBe([$panda->getKey()])
                ->and($roadTax->label)->toBe('Road tax')
                ->and($roadTax->personColumn)->toBe('person_id')
                ->and($roadTax->hasExpense)->toBeTrue();
        });
    });

    test('a policy has its label and its holder', function (): void {
        $policy = source('insurance_policies.expiry_date');

        expect($policy->label)->toBe('Policy renewal')
            ->and($policy->personColumn)->toBe('person_id')
            ->and($policy->activeStatuses)->toBe([InsurancePolicyStatus::Active, InsurancePolicyStatus::Expired]);
    });

    test('a subscription renews with a reminder only with a yearly fee', function (): void {
        inNewFamily(function (): void {
            $renewal = source('subscriptions.renewal_date');
            $yearly = cardOf($renewal);
            PlannedExpense::factory()->forCard($yearly)->create(['family_id' => CurrentFamily::id(), 'interval_unit' => IntervalUnit::Year]);
            $monthly = cardOf($renewal);
            PlannedExpense::factory()->forCard($monthly)->create(['family_id' => CurrentFamily::id(), 'interval_unit' => IntervalUnit::Month]);
            cardOf($renewal);

            // A yearly expense of another card does not count.
            $unrelated = cardOf(source('subscriptions.trial_end_date'));
            PlannedExpense::factory()->create(['family_id' => CurrentFamily::id(), 'interval_unit' => IntervalUnit::Year, 'card_type' => 'vehicle', 'card_id' => $unrelated->getKey()]);

            expect(foundBy($renewal))->toBe([$yearly->getKey()])
                ->and($renewal->label)->toBe('Renewal');
        });
    });

    test('the person of a subscription is its holder', function (): void {
        foreach (['subscriptions.trial_end_date', 'subscriptions.renewal_date'] as $field) {
            expect(source($field)->personColumn)->toBe('person_id')
                ->and(source($field)->model)->toBe(Subscription::class);
        }

        expect(source('belongings.warranty_expiry_date')->personColumn)->toBeNull()
            ->and(source('documents.expiry_date')->personColumn)->toBe('person_id');
    });

    test('a loan paid off keeps the last instalment only once it has passed', function (): void {
        inNewFamily(function (): void {
            $last = source('loans.end_date');
            $past = cardOf($last, ['status' => LoanStatus::PaidOff, 'end_date' => '2020-03-01']);
            cardOf($last, ['status' => LoanStatus::PaidOff, 'end_date' => '2099-01-01']);
            $active = cardOf($last, ['end_date' => '2099-01-01']);

            expect(foundBy($last))->toEqualCanonicalizing([$past->getKey(), $active->getKey()]);
        });
    });

    test('a loan paid off keeps the last instalment from the day after it', function (): void {
        inNewFamily(function (): void {
            $last = source('loans.end_date');
            cardOf($last, ['status' => LoanStatus::PaidOff, 'end_date' => '2026-10-14']);
            $yesterday = cardOf($last, ['status' => LoanStatus::PaidOff, 'end_date' => '2026-10-13']);

            expect(foundBy($last))->toBe([$yesterday->getKey()]);
        });
    });

    test('a card that expires by itself keeps its reminder', function (): void {
        inNewFamily(function (): void {
            $document = source('documents.expiry_date');
            $expired = cardOf($document, ['status' => DocumentStatus::Expired]);
            $active = cardOf($document);
            cardOf($document, ['status' => DocumentStatus::Archived]);
            $policy = cardOf(source('insurance_policies.expiry_date'), ['status' => InsurancePolicyStatus::Expired]);

            expect($document->model)->toBe(Document::class)
                ->and($document->label)->toBeNull()
                ->and(foundBy($document))->toEqualCanonicalizing([$expired->getKey(), $active->getKey()])
                ->and(foundBy(source('insurance_policies.expiry_date')))->toBe([$policy->getKey()]);
        });
    });

    test('every kind finds its card, not the ones without date or in another status', function (): void {
        foreach (DeadlineSources::all() as $source) {
            inNewFamily(function () use ($source): void {
                $card = cardOf($source);
                if ($source->field === 'subscriptions.renewal_date') {
                    PlannedExpense::factory()->forCard($card)->create(['family_id' => CurrentFamily::id(), 'interval_unit' => IntervalUnit::Year]);
                }
                cardOf($source, [$source->column => null]);
                $inactive = $source->activeStatuses === [] ? null : array_find($source->activeStatuses[0]::cases(), fn (BackedEnum $status): bool => ! in_array($status, $source->activeStatuses, true));
                if ($inactive instanceof BackedEnum) {
                    cardOf($source, ['status' => $inactive]);
                }

                expect(foundBy($source))->toBe([$card->getKey()], $source->field);
            });
        }
    });

    test('only the cards of the current family', function (): void {
        $mine = inNewFamily(fn (): Model => cardOf(source('vehicles.inspection_due_date')));
        inNewFamily(function () use ($mine): void {
            $theirs = cardOf(source('vehicles.inspection_due_date'));

            expect(foundBy(source('vehicles.inspection_due_date')))->toBe([$theirs->getKey()])
                ->and($theirs->getKey())->not->toBe($mine->getKey());
        });
    });
});
