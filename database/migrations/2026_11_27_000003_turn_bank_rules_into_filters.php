<?php

declare(strict_types=1);

use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankMatch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The rules *How it appears at the bank* (`bank_match`) of planned expenses and incomes become bank filters that pay
 * the due date: one filter for each planned item with a rule. A rule made only of words joined by `.*` becomes the
 * words of the filter, any other keeps its expression as the pattern. `bank_match` stays where it is. It only inserts
 * rows, so the previous version of the code is not disturbed, and it can be run again: a planned item that already has
 * a filter that pays it is skipped (so a migrated filter the family deleted comes back if it is run again).
 */
return new class extends Migration
{
    /** The shortest word a filter keeps as a word (`BankFilterCriteria::MIN_WORD`). */
    private const int MIN_WORD = 3;

    /**
     * Writes the filters, a chunk of planned items at a time. There is no signed-in family here, so the tables are
     * read directly.
     */
    public function up(): void
    {
        foreach ([['planned_expenses', 'planned_expense_id', 'expense'], ['planned_incomes', 'planned_income_id', 'income']] as [$table, $column, $direction]) {
            DB::table($table)
                ->whereNotNull('bank_match')
                ->where('bank_match', '!=', '')
                ->whereNotExists(static fn ($query) => $query->select(DB::raw(1))->from('bank_filters')->whereColumn("bank_filters.{$column}", "{$table}.id")->where('bank_filters.action', 'pay'))
                ->orderBy('id')
                ->chunkById(200, function ($items) use ($column, $direction): void {
                    $rows = [];

                    foreach ($items as $item) {
                        $words = $this->words((string) $item->bank_match);

                        $rows[] = [
                            'family_id' => $item->family_id,
                            'name' => mb_substr((string) $item->title, 0, 255),
                            'contains' => json_encode($words ?? [], JSON_UNESCAPED_UNICODE),
                            'excludes' => '[]',
                            'direction' => $direction,
                            'financial_account_id' => $this->bankAccount($item->financial_account_id),
                            'pattern' => $words === null ? $item->bank_match : null,
                            'fee_mode' => 'auto',
                            'action' => 'pay',
                            $column => $item->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    DB::table('bank_filters')->insert($rows);
                });
        }
    }

    /**
     * Removes nothing: from the day after this runs the filters can be edited, added to or deleted by the family, and
     * deleting them by planned item would take their work away. The column `bank_match` was never touched.
     */
    public function down(): void
    {
        // Intentionally empty
    }

    /**
     * The words of a rule when it is made only of literal words joined by `.*`, otherwise null so the expression stays
     * as it is. A literal is a letter, a digit, a space, or a character escaped with a backslash the way `preg_quote`
     * writes it (a backslash before a letter or digit is a regex escape like `\d` or `\b`, not a literal). Nothing
     * may follow a literal with a quantifier. The words must make safe criteria and each must have three letters or
     * digits at least: so the filter takes everything the rule took (words in any order are accepted).
     *
     * @param  string  $rule  the rule, a PCRE pattern without delimiters
     * @return list<string>|null
     */
    private function words(string $rule): ?array
    {
        $words = [];
        $word = '';
        $length = strlen($rule);

        for ($i = 0; $i < $length; $i++) {
            $char = $rule[$i];
            $next = $rule[$i + 1] ?? '';

            if ($char === '.' && $next === '*') {
                $following = $rule[$i + 2] ?? '';

                if ($following === '' || str_contains('*+?{', $following)) {
                    return null;
                }

                $words[] = $word;
                $word = '';
                $i++;

                continue;
            }

            if ($char === '\\') {
                if ($next === '' || preg_match('/^[\p{L}\p{N}\s]$/u', $next) === 1) {
                    return null;
                }

                $char = $next;
                $i++;
                $next = $rule[$i + 1] ?? '';
            } elseif (! ctype_alnum($char) && $char !== ' ' && ord($char) < 128) {
                return null;
            }

            if ($next !== '' && str_contains('*+?{', $next)) {
                return null;
            }

            $word .= $char;
        }

        $words[] = $word;

        foreach ($words as $each) {
            if (mb_strlen((string) preg_replace('/[^\p{L}\p{N}]+/u', '', BankMatch::normalize($each))) < self::MIN_WORD) {
                return null;
            }
        }

        return BankFilterCriteria::fromArray(['contains' => $words])->isSafe() ? $words : null;
    }

    /**
     * The account the rule reads today, from the raw account of the planned item, as `RuleAccount::of` gives it: the
     * account itself when linked to a bank, null (every account) otherwise, with no default account filled in.
     *
     * @param  int|string|null  $accountId  the account of the planned item
     */
    private function bankAccount(int|string|null $accountId): ?int
    {
        if ($accountId === null) {
            return null;
        }

        return DB::table('financial_accounts')->where('id', $accountId)->whereNotNull('bank_connection_id')->exists() ? (int) $accountId : null;
    }
};
