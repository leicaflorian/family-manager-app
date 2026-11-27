<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BankFilterAction;
use App\Models\BankTransaction;
use App\Support\Banks\BankFilterDraft;
use App\Support\Banks\BankGroup;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A group of transactions still to sort, as *From the bank* lists it, with what the system proposes for it.
 */
#[TypeScript]
final class BankGroupData extends Data
{
    /**
     * @param  string  $key  the key of the group
     * @param  string  $name  the counterparty, or the words of the text
     * @param  string  $kind  `expense` or `income`
     * @param  string  $account  the account, as «Account name ·· 1234»
     * @param  int  $count  how many transactions
     * @param  float  $min  the smallest amount, without sign
     * @param  float  $max  the largest amount, without sign
     * @param  string  $from  the day of the oldest (`yyyy-mm-dd`)
     * @param  string  $to  the day of the newest (`yyyy-mm-dd`)
     * @param  string|null  $proposal_action  `pay` or `create`, null when no expense looks like them
     * @param  string|null  $proposal_title  the title of the expense the filter would create
     * @param  string|null  $proposal_detail  what the proposal does in a few words («Varie · Satispay», the planned item)
     * @param  string|null  $recurring_url  the form of a new planned expense, filled in, when the group comes back every month and no proposal pays
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $kind,
        public readonly string $account,
        public readonly int $count,
        public readonly float $min,
        public readonly float $max,
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $proposal_action,
        public readonly ?string $proposal_title,
        public readonly ?string $proposal_detail,
        public readonly ?string $recurring_url,
    ) {}

    /**
     * The data of a group of the current family.
     *
     * @param  BankGroup  $group  the group
     * @param  BankFilterDraft|null  $proposal  what the system proposes for it
     * @param  string|null  $recurringUrl  where *It comes back every month* leads, null when the group does not
     */
    public static function fromGroup(BankGroup $group, ?BankFilterDraft $proposal, ?string $recurringUrl): self
    {
        $amounts = $group->transactions->map(static fn (BankTransaction $row): float => round(abs((float) $row->amount), 2));
        $days = $group->transactions->map(static fn (BankTransaction $row): string => (string) ($row->booked_on ?? $row->value_on)?->toDateString());
        $account = $group->transactions->first()?->financialAccount;

        return new self(
            $group->key,
            $group->name,
            $group->kind,
            $account === null ? '' : ($account->last_four_digits === null ? $account->name : $account->name.' ·· '.$account->last_four_digits),
            $group->transactions->count(),
            (float) $amounts->min(),
            (float) $amounts->max(),
            (string) $days->min(),
            (string) $days->max(),
            $proposal?->action?->value,
            $proposal?->title,
            $proposal?->detail,
            $proposal?->action === BankFilterAction::Pay ? null : $recurringUrl,
        );
    }
}
