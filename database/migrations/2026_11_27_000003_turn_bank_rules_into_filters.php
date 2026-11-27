<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The rules *How it appears at the bank* (`bank_match`) of planned expenses and incomes become bank filters that pay
 * the due date: one filter for each planned item with a rule. A rule made only of words joined by `.*` becomes the
 * words of the filter, any other keeps its expression as the pattern. `bank_match` stays where it is. It only inserts
 * rows, so the previous version of the code is not disturbed, and it can be run again: a planned item that already has
 * a filter that pays it is skipped.
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
                    $defaults = [];
                    $rows = [];

                    foreach ($items as $item) {
                        $account = $item->financial_account_id
                            ?? $defaults[$item->family_id] ??= $this->defaultAccount((int) $item->family_id);
                        $words = $this->words((string) $item->bank_match);

                        $rows[] = [
                            'family_id' => $item->family_id,
                            'name' => mb_substr((string) $item->title, 0, 255),
                            'contains' => json_encode($words ?? [], JSON_UNESCAPED_UNICODE),
                            'excludes' => '[]',
                            'direction' => $direction,
                            'financial_account_id' => $this->bankAccount($account),
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
     * The words of a rule when it is made only of words (letters and digits) joined by `.*`, each at least three
     * characters, otherwise null so the expression stays as it is.
     *
     * @param  string  $rule  the rule, a PCRE pattern without delimiters
     * @return list<string>|null
     */
    private function words(string $rule): ?array
    {
        $words = [];

        foreach (explode('.*', $rule) as $piece) {
            // `preg_quote` escapes with a backslash; take those away before judging the text
            $word = trim((string) preg_replace('/\\\\(.)/su', '$1', $piece));

            if (preg_match('/^[\p{L}\p{N}]+( [\p{L}\p{N}]+)*$/u', $word) !== 1 || mb_strlen($word) < self::MIN_WORD) {
                return null;
            }

            $words[] = $word;
        }

        return $words;
    }

    /**
     * The default account of a family: the active one marked as default, as `DefaultAccount` finds it.
     *
     * @param  int  $familyId  the family
     */
    private function defaultAccount(int $familyId): ?int
    {
        $id = DB::table('financial_accounts')->where('family_id', $familyId)->where('is_default', true)->where('status', 'active')->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * The account as `RuleAccount::of` reads it: itself when linked to a bank, null (every account) when not.
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
