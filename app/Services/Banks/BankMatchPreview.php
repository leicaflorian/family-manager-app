<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Data\BankTransactionData;
use App\Enums\BankTransactionStatus;
use App\Models\BankTransaction;
use App\Support\Banks\BankMatch;
use Carbon\CarbonImmutable;

/**
 * Which booked transactions of the last 90 days a rule *How it shows in the bank* recognises, so the person sees
 * what it would pay before saving it. Linked ones count too: they show what the rule already paid.
 */
final class BankMatchPreview
{
    /** How many days back the transactions are looked at. */
    public const int DAYS = 90;

    /** How many of the transactions are given back as rows. */
    public const int ROWS = 5;

    /** The most transactions read from the database for one preview. */
    private const int LIMIT = 2000;

    /**
     * The count and the five newest transactions the rule recognises, oldest of the five first.
     *
     * @param  string  $pattern  the rule, a PCRE pattern without delimiters
     * @param  string  $kind  `expense` looks at outgoing transactions, `income` at incoming ones
     * @param  int|null  $accountId  the account to look at, null for every account of the family
     * @return array{count: int, rows: list<BankTransactionData>}
     */
    public static function for(string $pattern, string $kind, ?int $accountId): array
    {
        $since = CarbonImmutable::today('Europe/Rome')->subDays(self::DAYS);

        $found = BankTransaction::query()
            ->with('financialAccount')
            ->where('status', BankTransactionStatus::Booked)
            ->where('booked_on', '>=', $since)
            ->where('amount', $kind === 'income' ? '>' : '<', 0)
            ->when($accountId !== null, static fn ($query) => $query->where('financial_account_id', $accountId))
            ->latest('booked_on')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->filter(static fn (BankTransaction $transaction): bool => BankMatch::matches($pattern, $transaction->text))
            ->values();

        return [
            'count' => $found->count(),
            'rows' => array_values(array_reverse($found->take(self::ROWS)->map(static fn (BankTransaction $row): BankTransactionData => BankTransactionData::fromTransaction($row))->all())),
        ];
    }
}
