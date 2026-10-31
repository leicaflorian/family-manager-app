<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\PlannedIncome;
use App\Services\CardSummaries;
use App\Support\Deadline;
use App\Support\PlannedKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One due date of a planned expense or income, as the rows and the sheets of the budget show it.
 */
#[TypeScript]
final class DeadlineData extends Data
{
    /**
     * @param  string  $kind  `expense` or `income`
     * @param  int  $planned_id  the id of the planned expense or income
     * @param  string  $title  what to call this due date, for example the title of the bill to pay
     * @param  string  $planned_title  the title of the planned expense or income
     * @param  string  $date  the real day, ISO, moved by an exception if there is one
     * @param  string  $original_date  the day the rules give, ISO: it identifies the due date in the addresses
     * @param  float  $amount  the amount due
     * @param  float|null  $base_amount  the amount of the planned item when an exception changed it
     * @param  bool  $estimated  whether the amount is an estimate
     * @param  string|null  $notes  the notes of the exceptions
     * @param  bool  $late  whether its day is over and nothing settles it
     * @param  bool  $skipped  whether an exception skips it: it is not paid
     * @param  PaymentData|null  $payment  the payment that settles it
     * @param  int|null  $account_id  the account of the planned item, else the default account of the family
     * @param  string|null  $account  the name of that account
     * @param  CardData|null  $card  the card the planned expense belongs to, for example the policy
     * @param  string|null  $income_type  the type of an income, for example `salary`
     * @param  string|null  $person  the first name of the person it is for
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $planned_id,
        public readonly string $title,
        public readonly string $planned_title,
        public readonly string $date,
        public readonly string $original_date,
        public readonly float $amount,
        public readonly ?float $base_amount,
        public readonly bool $estimated,
        public readonly ?string $notes,
        public readonly bool $late,
        public readonly bool $skipped,
        public readonly ?PaymentData $payment,
        public readonly ?int $account_id,
        public readonly ?string $account,
        public readonly ?CardData $card,
        public readonly ?string $income_type,
        public readonly ?string $person,
    ) {}

    /**
     * The data of a due date.
     *
     * It reads the account, the person and the card of the planned item and the account of the payment:
     * `Deadlines::between()` and `find()` load them for the whole collection, so a list does not query once per row.
     * The account is the one of the planned item or, when it has none, the default account of the family, so that
     * *I paid it* starts from it.
     *
     * @param  Deadline  $deadline  the due date
     * @param  CarbonImmutable  $today  today in Rome
     */
    public static function fromDeadline(Deadline $deadline, CarbonImmutable $today): self
    {
        $planned = $deadline->planned;
        $occurrence = $deadline->occurrence;
        $payment = $deadline->payment;
        $account = $deadline->account;
        $card = PlannedKind::card($planned);

        return new self(
            kind: $deadline->kind(),
            planned_id: $planned->id,
            title: $deadline->title,
            planned_title: $planned->displayTitle(),
            date: $occurrence->date->toDateString(),
            original_date: $occurrence->originalDate->toDateString(),
            amount: $occurrence->amount,
            base_amount: $occurrence->baseAmount,
            estimated: $occurrence->estimated,
            notes: $occurrence->notes,
            late: $deadline->isLate($today),
            skipped: $deadline->isSkipped(),
            payment: $payment === null ? null : PaymentData::fromMovement($payment),
            account_id: $account?->id,
            account: $account?->name,
            card: $card instanceof Model ? resolve(CardSummaries::class)->row($card, $today) : null,
            income_type: $planned instanceof PlannedIncome ? $planned->type->value : null,
            person: $planned->person?->first_name,
        );
    }
}
