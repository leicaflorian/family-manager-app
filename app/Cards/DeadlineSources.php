<?php

declare(strict_types=1);

namespace App\Cards;

use App\Enums\BelongingStatus;
use App\Enums\DocumentStatus;
use App\Enums\FilingStatus;
use App\Enums\InsurancePolicyStatus;
use App\Enums\IntervalUnit;
use App\Enums\LoanStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\VehicleStatus;
use App\Models\Belonging;
use App\Models\Document;
use App\Models\Filing;
use App\Models\HealthEvent;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\Subscription;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The list of the kinds of deadline of cards and documents: the one the Agenda,
 * the Home and the reminders all read, so that they never say different things.
 * It is the rewrite of `SOURCES` of the Directus extension `reminders-automation`.
 */
final class DeadlineSources
{
    /**
     * Every kind of deadline, in the order of the analysis.
     *
     * @return list<DeadlineSource>
     */
    public static function all(): array
    {
        return [
            new DeadlineSource('vehicles.road_tax_due_date', Vehicle::class, 'road_tax_due_date', [VehicleStatus::Active], null, __('Road tax'), 'person_id', true),
            new DeadlineSource('vehicles.inspection_due_date', Vehicle::class, 'inspection_due_date', [VehicleStatus::Active], null, __('Inspection'), 'person_id', true),
            // Expired too: the policy turns expired by itself, the renewal stays to be done.
            new DeadlineSource('insurance_policies.expiry_date', InsurancePolicy::class, 'expiry_date', [InsurancePolicyStatus::Active, InsurancePolicyStatus::Expired], null, __('Policy renewal'), 'person_id', true),
            new DeadlineSource('belongings.warranty_expiry_date', Belonging::class, 'warranty_expiry_date', [BelongingStatus::InUse], null, __('End of warranty')),
            // Expired too, like the policy; the row shows the title of the document.
            new DeadlineSource('documents.expiry_date', Document::class, 'expiry_date', [DocumentStatus::Active, DocumentStatus::Expired], null, null, 'person_id'),
            new DeadlineSource('filings.due_date', Filing::class, 'due_date', [FilingStatus::Draft], null, __('Filing deadline'), 'person_id'),
            new DeadlineSource('health_events.next_checkup_date', HealthEvent::class, 'next_checkup_date', [], null, __('Next checkup'), 'person_id'),
            new DeadlineSource('subscriptions.renewal_date', Subscription::class, 'renewal_date', [SubscriptionStatus::Active], self::withYearlyExpense(...), __('Renewal'), 'person_id'),
            new DeadlineSource('subscriptions.trial_end_date', Subscription::class, 'trial_end_date', [SubscriptionStatus::Active], null, __('End of the free trial'), 'person_id'),
            new DeadlineSource('loans.start_date', Loan::class, 'start_date', [LoanStatus::Active], null, __('First instalment'), 'person_id', true),
            // Paid off too, but only once the last instalment has passed: a loan closed by hand has nothing left to wait for.
            new DeadlineSource('loans.end_date', Loan::class, 'end_date', [LoanStatus::Active, LoanStatus::PaidOff], self::notPaidOffBeforeItsEnd(...), __('Last instalment'), 'person_id', true),
        ];
    }

    /**
     * The kind of deadline of a field.
     *
     * @param  string  $field  the key, like `vehicles.road_tax_due_date`
     */
    public static function find(string $field): ?DeadlineSource
    {
        foreach (self::all() as $source) {
            if ($source->field === $field) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Only the subscriptions that have a yearly planned expense: monthly
     * renewals would be too many.
     *
     * @param  Builder<Model>  $query  the query of subscriptions
     */
    private static function withYearlyExpense(Builder $query): void
    {
        $query->whereExists(
            PlannedExpense::query()
                ->where('card_type', (new Subscription)->getMorphClass())
                ->whereColumn('planned_expenses.card_id', 'subscriptions.id')
                ->where('interval_unit', IntervalUnit::Year->value)
        );
    }

    /**
     * A loan that is not paid off, or whose last instalment is already behind.
     *
     * @param  Builder<Model>  $query  the query of loans
     */
    private static function notPaidOffBeforeItsEnd(Builder $query): void
    {
        $today = CarbonImmutable::today('Europe/Rome')->toDateString();

        $query->where(static function (Builder $loans) use ($today): void {
            $loans->where('status', '!=', LoanStatus::PaidOff->value)->orWhere('end_date', '<', $today);
        });
    }
}
