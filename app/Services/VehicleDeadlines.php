<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use App\Data\CardDeadlineData;
use App\Enums\Feature;
use App\Enums\InsurancePolicyStatus;
use App\Models\InsurancePolicy;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The section *Deadlines* of a vehicle, one row per deadline, by date, the ones without a date last:
 * - each planned expense of the vehicle and of its policies, with its first due date not paid;
 * - each date of the card paid by a planned expense of its own (road tax, inspection) that still has none, with
 *   *Add the expense*;
 * - each of those dates missing on an active vehicle, with *Add the date*.
 *
 * With the Budget off the rows are only the dates of the card and the expiry of its active policies, without amounts.
 */
final readonly class VehicleDeadlines
{
    /** How many months back a due date not paid still counts, as {@see CardBudgets} reads them. */
    private const int MONTHS_BACK = 12;

    /** How many months ahead the due dates are read. */
    private const int MONTHS_AHEAD = 24;

    /**
     * @param  Deadlines  $deadlines  the due dates of the planned expenses
     * @param  CardBudgets  $budgets  the planned expenses of the vehicle and how often they repeat
     * @param  FamilyFeatures  $features  tells whether the family uses the Budget
     */
    public function __construct(private Deadlines $deadlines, private CardBudgets $budgets, private FamilyFeatures $features) {}

    /**
     * The rows of the section *Deadlines* of a vehicle.
     *
     * @param  Vehicle  $vehicle  the vehicle
     * @param  User  $user  whoever looks at the card, for the links to add what is missing
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<CardDeadlineData>
     */
    public function of(Vehicle $vehicle, User $user, CarbonImmutable $today): array
    {
        $budgetOn = $this->features->on(Feature::Budget);
        $sources = DeadlineSources::ownExpenseFor(Vehicle::class);
        // A date missing on the card is asked for in its own row, which takes the place of the planned expense that pays it
        $missing = array_values(array_filter($sources, fn (DeadlineSource $source): bool => $this->isMissing($source, $vehicle)));
        $planned = $budgetOn ? $this->budgets->vehicleExpenses($vehicle) : [];

        $rows = [
            ...($budgetOn ? $this->expenseRows($planned, $missing, $today) : $this->policyRows($vehicle)),
            ...$this->dateRows($vehicle, $sources, $planned, $user, $today, $budgetOn),
            ...array_map(static fn (DeadlineSource $source): CardDeadlineData => new CardDeadlineData(
                label: (string) $source->label,
                date: null,
                add_date_href: $user->can('update', $vehicle) ? route('cards.edit', ['type' => 'vehicle', 'id' => $vehicle->id]) : null,
            ), $missing),
        ];

        // A stable sort: the rows without a date stay last, in their order
        usort($rows, static fn (CardDeadlineData $a, CardDeadlineData $b): int => [$a->date === null, $a->date] <=> [$b->date === null, $b->date]);

        return $rows;
    }

    /**
     * Each planned expense with its first due date not paid. One that pays a date of the card is named after the
     * date and says nothing more; one of a policy says the policy; the others say how often they repeat.
     *
     * @param  list<PlannedExpense>  $planned  the planned expenses of the vehicle and of its policies
     * @param  list<DeadlineSource>  $missing  the dates missing on the card, whose planned expense is left out
     * @param  CarbonImmutable  $today  today in Rome
     * @return list<CardDeadlineData>
     */
    private function expenseRows(array $planned, array $missing, CarbonImmutable $today): array
    {
        $skipped = array_map(static fn (DeadlineSource $source): string => $source->field, $missing);
        $planned = array_values(array_filter($planned, static fn (PlannedExpense $item): bool => ! in_array($item->deadline_field, $skipped, true)));
        Collection::make($planned)->load('card');
        $open = array_values(array_filter(
            $this->deadlines->between($planned, $today->subMonthsNoOverflow(self::MONTHS_BACK), $today->addMonthsNoOverflow(self::MONTHS_AHEAD)),
            static fn (Deadline $deadline): bool => $deadline->isDue(),
        ));
        $rows = [];

        foreach ($planned as $item) {
            $next = array_find($open, static fn (Deadline $deadline): bool => $deadline->planned->is($item));

            if (! $next instanceof Deadline) {
                continue;
            }

            $source = $item->deadline_field === null ? null : DeadlineSources::find($item->deadline_field);
            $own = $source instanceof DeadlineSource && $source->ownExpense;

            $rows[] = new CardDeadlineData(
                label: $own ? (string) $source->label : $item->title,
                date: $next->occurrence->date->toDateString(),
                detail: match (true) {
                    $own => null,
                    $item->card instanceof InsurancePolicy => $item->card->title,
                    default => $this->budgets->every($item, withDay: false),
                },
                amount: round($next->occurrence->amount, 2),
                estimated: $next->occurrence->estimated,
                href: route('budget.planned.show', ['kind' => 'expense', 'id' => $item->id]),
            );
        }

        return $rows;
    }

    /**
     * With the Budget off: the expiry of each active policy of the vehicle, which opens the policy.
     *
     * @param  Vehicle  $vehicle  the vehicle
     * @return list<CardDeadlineData>
     */
    private function policyRows(Vehicle $vehicle): array
    {
        $label = __('Policy renewal');

        return array_values(InsurancePolicy::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', InsurancePolicyStatus::Active)
            ->whereNotNull('expiry_date')
            ->get()
            ->map(static fn (InsurancePolicy $policy): CardDeadlineData => new CardDeadlineData(
                label: $label,
                date: $policy->expiry_date?->toDateString(),
                detail: $policy->title,
                href: route('cards.show', ['type' => 'insurance_policy', 'id' => $policy->id]),
            ))
            ->all());
    }

    /**
     * The dates of the card paid by a planned expense of its own. With the Budget on, only the ones still without
     * that planned expense, with *Add the expense* for whoever may create one.
     *
     * @param  Vehicle  $vehicle  the vehicle
     * @param  list<DeadlineSource>  $sources  the dates paid by a planned expense of their own
     * @param  list<PlannedExpense>  $planned  the planned expenses of the vehicle and of its policies
     * @param  User  $user  whoever looks at the card
     * @param  CarbonImmutable  $today  today in Rome
     * @param  bool  $budgetOn  whether the family uses the Budget
     * @return list<CardDeadlineData>
     */
    private function dateRows(Vehicle $vehicle, array $sources, array $planned, User $user, CarbonImmutable $today, bool $budgetOn): array
    {
        $canAdd = $budgetOn && $user->can('create', PlannedExpense::class);
        $rows = [];

        foreach ($sources as $source) {
            $date = $source->current($vehicle, $today);

            if (! $date instanceof CarbonImmutable || ($budgetOn && $this->hasOwnExpense($source, $vehicle, $planned, $today))) {
                continue;
            }

            $rows[] = new CardDeadlineData(
                label: (string) $source->label,
                date: $date->toDateString(),
                add_expense_href: $canAdd ? route('budget.planned.create', ['kind' => 'expense', 'card' => 'vehicle:'.$vehicle->id, 'deadline' => $source->field]) : null,
            );
        }

        return $rows;
    }

    /**
     * Whether a planned expense of the vehicle in progress pays the date, as {@see Reminders\MissingExpenses} reads it.
     *
     * @param  DeadlineSource  $source  the date
     * @param  Vehicle  $vehicle  the vehicle
     * @param  list<PlannedExpense>  $planned  the planned expenses of the vehicle and of its policies
     * @param  CarbonImmutable  $today  today in Rome: a planned expense that ends today is still in progress
     */
    private function hasOwnExpense(DeadlineSource $source, Vehicle $vehicle, array $planned, CarbonImmutable $today): bool
    {
        return array_any($planned, static fn (PlannedExpense $item): bool => $item->deadline_field === $source->field
            && $item->card_type === $vehicle->getMorphClass() && $item->card_id === $vehicle->id
            && ($item->end_date === null || $item->end_date->toDateString() >= $today->toDateString()));
    }

    /**
     * Whether the date is missing on a vehicle that still has it: empty, and the vehicle in a status in which it counts.
     *
     * @param  DeadlineSource  $source  the date
     * @param  Vehicle  $vehicle  the vehicle
     */
    private function isMissing(DeadlineSource $source, Vehicle $vehicle): bool
    {
        return $vehicle->getAttribute($source->column) === null
            && ($source->activeStatuses === [] || in_array($vehicle->status, $source->activeStatuses, true));
    }
}
