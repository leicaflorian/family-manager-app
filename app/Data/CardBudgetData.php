<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The payments of a card: the next due date in the box at the top, the figures, the due dates still open, the
 * planned expenses (or incomes) linked to it and the payments that settled them, by year.
 */
#[TypeScript]
final class CardBudgetData extends Data
{
    /**
     * @param  string  $kind  `expense`, or `income` for an employment: what its planned items are
     * @param  DeadlineData|null  $next  the due date of the box at the top: the oldest one still open
     * @param  string  $next_label  what the box is called, for example «Renewal»; empty without a box
     * @param  int|null  $installment_number  the number of the next instalment of a loan, as in «Instalment 33 of 48»
     * @param  int|null  $installments_count  how many instalments the loan has
     * @param  int|null  $installments_paid  how many instalments of the loan are paid
     * @param  float|null  $paid_amount  what the instalments paid come to
     * @param  float|null  $left_amount  what is left to pay of the loan
     * @param  list<CardNumberData>  $numbers  the figures
     * @param  string|null  $due_label  the title of the open due dates, null when the card does not list them
     * @param  list<DeadlineData>  $due  the open due dates, by date
     * @param  string|null  $planned_label  «Planned expenses» or «Planned income», null when the card has no such section
     * @param  list<PlannedRowData>  $planned  the planned items linked to the card
     * @param  bool  $can_add_planned  whether whoever looks may add a planned item to the card
     * @param  string|null  $payments_label  the title of the payments, for example «Bills paid», null without the section
     * @param  string  $payments_more  the link to all the payments, year by year
     * @param  list<PaymentYearData>  $payments  the payments of the planned items, by year, the latest first
     * @param  list<DeadlineData>  $pay  for *Record payment*: the oldest open due date of each planned item
     * @param  bool  $can_pay  whether whoever looks may record a payment, and the card has a planned item to pay
     * @param  bool  $can_add_expense  whether whoever looks may add an expense linked to the card, on the cards that only
     *                                 have expenses linked to them (a belonging, a maintenance job, a filing, a health event)
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?DeadlineData $next,
        public readonly string $next_label,
        public readonly ?int $installment_number,
        public readonly ?int $installments_count,
        public readonly ?int $installments_paid,
        public readonly ?float $paid_amount,
        public readonly ?float $left_amount,
        #[DataCollectionOf(CardNumberData::class)]
        public readonly array $numbers,
        public readonly ?string $due_label,
        #[DataCollectionOf(DeadlineData::class)]
        public readonly array $due,
        public readonly ?string $planned_label,
        #[DataCollectionOf(PlannedRowData::class)]
        public readonly array $planned,
        public readonly bool $can_add_planned,
        public readonly ?string $payments_label,
        public readonly string $payments_more,
        #[DataCollectionOf(PaymentYearData::class)]
        public readonly array $payments,
        #[DataCollectionOf(DeadlineData::class)]
        public readonly array $pay,
        public readonly bool $can_pay,
        public readonly bool $can_add_expense,
    ) {}
}
