<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\FinancialAccount;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\CardSummaries;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A planned expense or income as a row of *Planned expenses* and as the head of its page.
 */
#[TypeScript]
final class PlannedRowData extends Data
{
    /**
     * @param  string  $kind  `expense` or `income`
     * @param  int  $id  the id of the planned expense or income
     * @param  string  $title  the title; an income without one takes the name of its type
     * @param  CardData|null  $card  the card it refers to, for example the policy
     * @param  string  $unit  `week`, `month` or `year`
     * @param  int  $interval  every how many units
     * @param  string  $start_date  the first due date, ISO
     * @param  string|null  $end_date  the last day it repeats, ISO
     * @param  bool  $recurring  whether it repeats
     * @param  string|null  $person  the first name of the person it is for; empty means the whole family
     * @param  string|null  $next_date  the next due date that is not late and not paid, ISO
     * @param  string|null  $account  the name of its account, else of the default account of the family
     * @param  float  $amount  the amount of every due date
     * @param  bool  $estimated  whether the amount is an estimate
     * @param  string|null  $icon  the icon of the category
     * @param  string|null  $color  the colour of the category
     * @param  bool  $ended  whether its last day is over
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $id,
        public readonly string $title,
        public readonly ?CardData $card,
        public readonly string $unit,
        public readonly int $interval,
        public readonly string $start_date,
        public readonly ?string $end_date,
        public readonly bool $recurring,
        public readonly ?string $person,
        public readonly ?string $next_date,
        public readonly ?string $account,
        public readonly float $amount,
        public readonly bool $estimated,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly bool $ended,
    ) {}

    /**
     * The row of a planned expense or income. It reads the person, the card and, for an expense, the category, so
     * the caller loads them for a whole list.
     *
     * @param  PlannedExpense|PlannedIncome  $planned  the planned item
     * @param  CarbonImmutable|null  $next  the next due date that is not late and not paid
     * @param  FinancialAccount|null  $default  the default account of the family, for a planned item without one
     * @param  CarbonImmutable  $today  today in Rome
     */
    public static function fromPlanned(PlannedExpense|PlannedIncome $planned, ?CarbonImmutable $next, ?FinancialAccount $default, CarbonImmutable $today): self
    {
        $card = PlannedKind::card($planned);
        $category = $planned instanceof PlannedExpense ? $planned->category : null;

        return new self(
            kind: $planned instanceof PlannedExpense ? 'expense' : 'income',
            id: $planned->id,
            title: $planned->displayTitle(),
            card: $card instanceof Model ? resolve(CardSummaries::class)->row($card, $today) : null,
            unit: $planned->interval_unit->value,
            interval: max(1, (int) $planned->interval),
            start_date: $planned->start_date?->toDateString() ?? '',
            end_date: $planned->end_date?->toDateString(),
            recurring: (bool) $planned->is_recurring,
            person: $planned->person?->first_name,
            next_date: $next?->toDateString(),
            account: ($planned->financialAccount ?? $default)?->name,
            amount: round((float) $planned->amount, 2),
            estimated: (bool) $planned->is_estimated,
            icon: $category?->icon,
            color: $category?->color,
            ended: $planned->end_date !== null && $planned->end_date->toDateString() < $today->toDateString(),
        );
    }
}
