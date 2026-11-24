<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the Home shows: what to pay in the next 30 days, the deadlines, the
 * incomes on their way, the files waiting, what is missing and how to begin.
 */
#[TypeScript]
final class HomeData extends Data
{
    /**
     * @param  string  $first_name  the first name of whoever signed in
     * @param  string  $today  today in Rome, `yyyy-mm-dd`
     * @param  float|null  $total  the sum of the payments to make, late ones included; null with the Budget off, when the Home shows no money
     * @param  int  $payments  how many payments
     * @param  int  $late  how many of them are late
     * @param  list<AgendaEntryData>  $entries  payments and deadlines, the late ones first, ten at most
     * @param  bool  $more  whether there were more than ten
     * @param  list<AgendaEntryData>  $incoming  the incomes of the next 30 days
     * @param  int  $pending_count  how many files wait for their document, copies left out as in the red number of *Upload*, for the box that leads to *To sort*
     * @param  list<ChecklistStepData>  $checklist  the steps of *To begin*, without the ones the family took away; empty when all are done or the account hid the list
     * @param  list<MissingDocumentData>  $missing  the documents the cards expect and do not have; empty when nothing is missing
     * @param  list<HomeMailData>  $mails  the last mails that brought files, three at most; empty when none
     * @param  bool  $can_change_features  whether the account can turn the Budget and the mail on (*Settings › What you use*), for the link at the end of *To begin*
     */
    public function __construct(
        public readonly string $first_name,
        public readonly string $today,
        public readonly ?float $total,
        public readonly int $payments,
        public readonly int $late,
        public readonly array $entries,
        public readonly bool $more,
        public readonly array $incoming,
        public readonly int $pending_count,
        public readonly array $checklist,
        #[DataCollectionOf(MissingDocumentData::class)]
        public readonly array $missing,
        #[DataCollectionOf(HomeMailData::class)]
        public readonly array $mails,
        public readonly bool $can_change_features,
    ) {}
}
