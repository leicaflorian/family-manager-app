<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Enums\BankFilterAction;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Banks\BankFilterDraft;
use App\Support\Banks\BankGroup;
use App\Support\Banks\BankMatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What the system proposes a filter does with a group, taken from what the family already has: a planned expense or
 * income that the movements pay, or a recorded expense they look like. It only looks: nothing is saved or linked.
 */
final class BankGroupProposals
{
    /** The days between a movement and a recorded expense of the same amount that make them look alike. */
    private const int DAYS = 7;

    /** The months back the recorded expenses are looked at. */
    private const int MONTHS = 12;

    /** The shortest name of an organization that counts as found in a text (a shorter one is in everything). */
    private const int MIN_NAME = 3;

    /**
     * The proposal for a group. A planned expense (or income) that is not finished, that no filter pays, and whose
     * organization is in the text of every movement or whose amount is the amount of every movement → pay its due
     * date. Otherwise the expenses of the last twelve months whose organization is in the text, or whose amount is
     * that of a movement within seven days of its date → create the expense like the latest of them (title without
     * the month, category, organization and person). Otherwise nothing. The draft has only the action and what it
     * needs; `BankFilterDraft::fromGroup()` adds the words.
     *
     * @param  BankGroup  $group  the group
     */
    public function for(BankGroup $group): ?BankFilterDraft
    {
        $organizations = $this->organizationsIn($group);

        return $this->pay($group, $organizations) ?? $this->create($group, $organizations);
    }

    /**
     * The planned item to pay, when there is one.
     *
     * @param  BankGroup  $group  the group
     * @param  Collection<int, Organization>  $organizations  the organizations that are in the text of every movement
     */
    private function pay(BankGroup $group, Collection $organizations): ?BankFilterDraft
    {
        $expense = $group->kind === 'expense';
        $column = $expense ? 'planned_expense_id' : 'planned_income_id';
        $taken = array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, BankFilter::query()->whereNotNull($column)->pluck($column)->all()));

        /** @var Builder<PlannedExpense>|Builder<PlannedIncome> $query */
        $query = $expense ? PlannedExpense::query() : PlannedIncome::query();
        /** @var list<PlannedExpense|PlannedIncome> $candidates */
        $candidates = $this->open($query, $taken)->get()->all();

        $found = array_find($candidates, static fn (PlannedExpense|PlannedIncome $planned): bool => $organizations->contains('id', $planned->organization_id))
            ?? array_find($candidates, fn (PlannedExpense|PlannedIncome $planned): bool => is_numeric($planned->amount) && $this->everyAmountIs($group, $planned->amount));

        if ($found === null) {
            return null;
        }

        $title = trim((string) $found->title);

        return new BankFilterDraft(
            name: $group->name,
            direction: $group->kind,
            action: BankFilterAction::Pay,
            plannedExpenseId: $found instanceof PlannedExpense ? $found->id : null,
            plannedIncomeId: $found instanceof PlannedIncome ? $found->id : null,
            detail: $title !== '' ? $title : $found->organization?->name,
        );
    }

    /**
     * The planned items that can still be paid by a proposal: not finished, no filter pays them, no rule of the old
     * kind, oldest first.
     *
     * @param  Builder<PlannedExpense>|Builder<PlannedIncome>  $query  the planned items of the family
     * @param  list<int>  $taken  the ids of the ones a filter pays already
     * @return Builder<PlannedExpense>|Builder<PlannedIncome>
     */
    private function open(Builder $query, array $taken): Builder
    {
        return $query
            ->whereNotIn('id', $taken)
            ->where(static fn (Builder $query) => $query->whereNull('end_date')->orWhere('end_date', '>=', CarbonImmutable::today('Europe/Rome')->toDateString()))
            ->where(static fn (Builder $query) => $query->whereNull('bank_match')->orWhere('bank_match', ''))
            ->orderBy('id');
    }

    /**
     * The expense to create like a recorded one, when there is one. Only for money that went out: recorded expenses
     * are what the proposal is made from.
     *
     * @param  BankGroup  $group  the group
     * @param  Collection<int, Organization>  $organizations  the organizations that are in the text of every movement
     */
    private function create(BankGroup $group, Collection $organizations): ?BankFilterDraft
    {
        if ($group->kind !== 'expense') {
            return null;
        }

        $since = CarbonImmutable::today('Europe/Rome')->subMonths(self::MONTHS)->toDateString();
        $cents = $group->transactions->map(fn (BankTransaction $row): int => $this->cents($row->amount))->unique()->values();
        $amounts = $cents->map(static fn (int $value): string => number_format($value / 100, 2, '.', ''))->all();

        $like = Expense::query()
            ->with(['category', 'organization'])
            ->whereNotNull('date')
            ->where('date', '>=', $since)
            ->where(static fn (Builder $query) => $query->whereIn('organization_id', $organizations->pluck('id')->all())->orWhereIn('amount', $amounts))
            ->latest('date')
            ->latest('id')
            ->get()
            ->first(fn (Expense $expense): bool => $organizations->contains('id', $expense->organization_id) || $this->closeToAMovement($group, $expense));

        if (! $like instanceof Expense) {
            return null;
        }

        [$title, $withMonth] = $this->withoutMonth($like->title);
        $detail = array_filter([$like->category?->name, $like->organization?->name], static fn (?string $part): bool => $part !== null && $part !== '');

        return new BankFilterDraft(
            name: $group->name,
            direction: $group->kind,
            action: BankFilterAction::Create,
            title: $title,
            titleWithMonth: $withMonth,
            categoryId: $like->category_id,
            organizationId: $like->organization_id,
            personId: $like->person_id,
            detail: $detail === [] ? null : implode(' · ', $detail),
        );
    }

    /**
     * The organizations of the family whose name is in the text (and the counterparty) of every movement of the group.
     *
     * @param  BankGroup  $group  the group
     * @return Collection<int, Organization>
     */
    private function organizationsIn(BankGroup $group): Collection
    {
        $texts = $group->transactions->map(static fn (BankTransaction $row): string => BankMatch::normalize($row->text.' '.$row->counterparty));

        return Organization::query()->get()->filter(static function (Organization $organization) use ($texts): bool {
            $name = BankMatch::normalize($organization->name);

            return mb_strlen($name) >= self::MIN_NAME && $texts->every(static fn (string $text): bool => str_contains($text, $name));
        })->values();
    }

    /**
     * Whether every movement of the group has this amount, in cents.
     *
     * @param  BankGroup  $group  the group
     * @param  numeric  $amount  the amount, with or without sign
     */
    private function everyAmountIs(BankGroup $group, mixed $amount): bool
    {
        $cents = $this->cents($amount);

        return $group->transactions->every(fn (BankTransaction $row): bool => $this->cents($row->amount) === $cents);
    }

    /**
     * Whether an expense has the amount and a date within seven days of a movement of the group.
     *
     * @param  BankGroup  $group  the group
     * @param  Expense  $expense  the recorded expense
     */
    private function closeToAMovement(BankGroup $group, Expense $expense): bool
    {
        $day = CarbonImmutable::parse($expense->date?->toDateString() ?? 'today', 'UTC');

        return $group->transactions->contains(function (BankTransaction $row) use ($expense, $day): bool {
            $booked = $row->booked_on ?? $row->value_on;

            return $booked !== null
                && $this->cents($row->amount) === $this->cents($expense->amount)
                && abs($day->diffInDays(CarbonImmutable::parse($booked->toDateString(), 'UTC'))) <= self::DAYS;
        });
    }

    /**
     * The title without the month and year at its end («Ricarica Satispay ottobre 2026» → «Ricarica Satispay»),
     * and whether there was one.
     *
     * @param  string  $title  the title of a recorded expense
     * @return array{0: string, 1: bool}
     */
    private function withoutMonth(string $title): array
    {
        $names = [];

        foreach (['it', 'en'] as $locale) {
            foreach (range(1, 12) as $month) {
                $date = CarbonImmutable::parse('2026-'.$month.'-01')->locale($locale);
                $names[] = preg_quote($date instanceof CarbonImmutable ? $date->translatedFormat('F') : '', '/');
            }
        }

        $stripped = trim((string) preg_replace('/\s+(?:'.implode('|', array_unique(array_filter($names))).')(?:\s+\d{4})?$/iu', '', $title));

        return $stripped === '' || $stripped === $title ? [$title, false] : [$stripped, true];
    }

    /**
     * An amount in cents, without the sign.
     *
     * @param  numeric  $amount  the amount
     */
    private function cents(mixed $amount): int
    {
        return (int) round(abs((float) $amount) * 100);
    }
}
