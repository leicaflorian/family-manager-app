<?php

declare(strict_types=1);

namespace App\Data\Reading;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a file does with its expense (or income, for a payslip): the values with the corrections of the person, the
 * movement already recorded it is linked to instead, what changes on it, "already paid" or "to pay", the due date a
 * payment receipt pays, and what is still missing.
 */
#[TypeScript]
final class MovementProposalData extends Data
{
    /**
     * @param  MovementValuesData  $values  the values, with the corrections of the person
     * @param  bool  $suggested  whether the box starts ticked
     * @param  array{id: int, title: string, date: string}|null  $duplicate  the movement already recorded (or picked by hand) the document is linked to instead
     * @param  list<string>  $changes  what changes on that expense, as sentences (`Importo: 481,00 € (480,00 € più 1,00 € di commissione)`)
     * @param  list<string>  $warnings  what does not change on it and why
     * @param  int  $others  the other expenses a payment receipt could pay, as close as the one found
     * @param  string|null  $blocked  what is missing to create the movement; null when nothing is
     * @param  DueData|null  $due  "already paid" or "to pay"; null for an income and for a movement already recorded
     * @param  array{planned: int, title: string, date: string, occurrence: string, others: int}|null  $pays  the unpaid due date a payment receipt pays: the planned expense, its title, the date shown, the original date, the other ones that fit
     * @param  list<array{id: int, title: string, date: string, amount: float}>  $candidates  the expenses (or incomes) within a month of the date, the closest first, to pick another by hand
     */
    public function __construct(
        public readonly MovementValuesData $values,
        public readonly bool $suggested,
        public readonly ?array $duplicate,
        public readonly array $changes,
        public readonly array $warnings,
        public readonly int $others,
        public readonly ?string $blocked,
        public readonly ?DueData $due,
        public readonly ?array $pays,
        public readonly array $candidates,
    ) {}
}
