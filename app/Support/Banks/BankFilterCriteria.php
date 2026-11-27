<?php

declare(strict_types=1);

namespace App\Support\Banks;

use App\Models\BankFilter;

/**
 * What a bank filter takes, apart from what it does: the words, the direction, the account, the amounts and the
 * pattern of an advanced filter. The form, the preview and the matcher all read it from here.
 */
final readonly class BankFilterCriteria
{
    /** A word shorter than this takes too much to count as a word of a filter. */
    public const int MIN_WORD = 3;

    /**
     * @param  list<string>  $contains  the words or phrases the text must all have
     * @param  list<string>  $excludes  the words or phrases the text must not have
     * @param  string  $direction  `expense` or `income`
     * @param  int|null  $accountId  the account to read, null for every account
     * @param  numeric-string|null  $amountMin  the smallest amount, without sign
     * @param  numeric-string|null  $amountMax  the largest amount, without sign
     * @param  string|null  $pattern  the expression of an advanced filter, without delimiters
     */
    public function __construct(
        public array $contains = [],
        public array $excludes = [],
        public string $direction = 'expense',
        public ?int $accountId = null,
        public ?string $amountMin = null,
        public ?string $amountMax = null,
        public ?string $pattern = null,
    ) {}

    /**
     * The criteria of a saved filter.
     *
     * @param  BankFilter  $filter  the filter
     */
    public static function fromFilter(BankFilter $filter): self
    {
        return self::fromArray([
            'contains' => $filter->contains,
            'excludes' => $filter->excludes,
            'direction' => $filter->direction,
            'accountId' => $filter->financial_account_id,
            'amountMin' => $filter->amount_min,
            'amountMax' => $filter->amount_max,
            'pattern' => $filter->pattern,
        ]);
    }

    /**
     * The criteria of a form or a preview, whatever the shape of what arrives.
     *
     * @param  array<string, mixed>  $data  `contains`, `excludes`, `direction`, `accountId`, `amountMin`, `amountMax`, `pattern`
     */
    public static function fromArray(array $data): self
    {
        $pattern = $data['pattern'] ?? null;
        $direction = $data['direction'] ?? null;

        return new self(
            contains: self::words($data['contains'] ?? null),
            excludes: self::words($data['excludes'] ?? null),
            direction: $direction === 'income' ? 'income' : 'expense',
            accountId: is_numeric($data['accountId'] ?? null) ? (int) $data['accountId'] : null,
            amountMin: self::amount($data['amountMin'] ?? null),
            amountMax: self::amount($data['amountMax'] ?? null),
            pattern: is_string($pattern) && trim($pattern) !== '' ? $pattern : null,
        );
    }

    /**
     * Whether the criteria can be used: at least one word of three letters or more among the words, or a pattern that
     * can be saved. Anything else would take every transaction, and takes none.
     */
    public function isSafe(): bool
    {
        if ($this->pattern !== null) {
            return BankMatch::valid($this->pattern);
        }

        return array_any($this->contains, static fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD);
    }

    /**
     * The words as a clean list of normalised-ready texts: strings only, trimmed, not empty.
     *
     * @param  mixed  $value  what arrived, a list or not
     * @return list<string>
     */
    private static function words(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $words = [];

        foreach ($value as $word) {
            if (is_string($word) && trim($word) !== '') {
                $words[] = trim($word);
            }
        }

        return $words;
    }

    /**
     * An amount as a number with no sign, or null.
     *
     * @param  mixed  $value  what arrived
     * @return numeric-string|null
     */
    private static function amount(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = abs((float) $value);

        return number_format($number, 2, '.', '');
    }
}
