<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Data\BankPlannedChoiceData;
use App\Data\BankRecordedChoiceData;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Deadline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The search of *It belongs to an expense*: what a bank transaction can be linked to, among every planned expense (or
 * income, for a credit) and every expense (or income) somebody already recorded. Pure reading, in the family of the
 * transaction; the server does it because recorded movements are thousands.
 */
final readonly class BankLinkSearch
{
    /** The most rows each of the two lists has. */
    private const int LIMIT = 30;

    /** How many days back the empty search looks for a recorded movement of the same amount. */
    private const int RECENT_DAYS = 30;

    public function __construct(private BankMatcher $matcher) {}

    /**
     * The two lists for a query.
     *
     * - `planned`: the planned items, ended ones too, whose title or organization has every word of the query, in order
     *   of title, each with the due date it would settle (`BankMatcher::deadlineAnywhere()`). With an empty query: the
     *   ones with a due date within reach of `BankMatcher::deadline()`.
     * - `recorded`: the expenses (or incomes) no other bank transaction has, by the words of the title, the name of the
     *   organization or, when the query is a number written the Italian way, the amount; the ones with the amount of the
     *   transaction first, then by the closest date. With an empty query: the same amount of the last 30 days.
     *
     * A transaction that is not booked in euros has nothing to link to.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  string  $query  What the person wrote.
     * @return array{planned: list<BankPlannedChoiceData>, recorded: list<BankRecordedChoiceData>}
     */
    public function search(BankTransaction $transaction, string $query): array
    {
        if (! $transaction->booked_on instanceof CarbonImmutable || $transaction->currency !== 'EUR') {
            return ['planned' => [], 'recorded' => []];
        }

        $words = $this->words($query);

        return ['planned' => $this->planned($transaction, $words), 'recorded' => $this->recorded($transaction, $words, $this->cents($query))];
    }

    /**
     * The planned items of the kind of the transaction that match the words.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  list<string>  $words  The words of the query.
     * @return list<BankPlannedChoiceData>
     */
    private function planned(BankTransaction $transaction, array $words): array
    {
        $today = CarbonImmutable::today('Europe/Rome');
        $query = $transaction->kind() === 'expense' ? PlannedExpense::query() : PlannedIncome::query();
        $query = $this->matching($query->with('organization'), $words)->orderBy('title')->orderBy('id');
        $choices = [];

        // Without words every planned item is looked at, as the old list did; with words, only the first 30 that match
        foreach ($words === [] ? $query->get() : $query->limit(self::LIMIT)->get() as $planned) {
            if ($words === [] && ! $this->matcher->deadline($transaction, $planned) instanceof Deadline) {
                continue;
            }

            $deadline = $this->matcher->deadlineAnywhere($transaction, $planned);

            if ($deadline instanceof Deadline) {
                $choices[] = BankPlannedChoiceData::fromDeadline($planned, $deadline, $today);
            }

            if (count($choices) === self::LIMIT) {
                break;
            }
        }

        return $choices;
    }

    /**
     * The recorded expenses or incomes of the kind of the transaction that no other bank transaction has.
     *
     * @param  BankTransaction  $transaction  The transaction.
     * @param  list<string>  $words  The words of the query.
     * @param  int|null  $cents  The query as an amount, when it is a number written the Italian way.
     * @return list<BankRecordedChoiceData>
     */
    private function recorded(BankTransaction $transaction, array $words, ?int $cents): array
    {
        $isExpense = $transaction->kind() === 'expense';
        $column = $isExpense ? 'expense_id' : 'income_id';
        $own = (int) round(abs((float) $transaction->amount) * 100);
        $day = $transaction->booked_on?->toDateString() ?? CarbonImmutable::today('Europe/Rome')->toDateString();
        $query = ($isExpense ? Expense::query() : Income::query())
            ->with('organization')
            ->whereNotIn('id', BankTransaction::query()->whereNotNull($column)->select($column));

        if ($words === []) {
            $today = CarbonImmutable::today('Europe/Rome');
            $query->whereBetween('date', [$today->subDays(self::RECENT_DAYS)->toDateString(), $today->toDateString()])
                ->whereRaw('round(amount * 100) = ?', [$own]);
        } else {
            $query->where(function (Builder $query) use ($words, $cents): void {
                $query->where(fn (Builder $query): Builder => $this->matching($query, $words));

                if ($cents !== null) {
                    $query->orWhereRaw('round(amount * 100) = ?', [$cents]);
                }
            });
        }

        /** @var list<Expense|Income> $found */
        $found = $query
            ->orderByRaw('round(amount * 100) = ? desc', [$own])
            ->orderByRaw('abs(datediff(date, ?))', [$day])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->all();

        return array_map(BankRecordedChoiceData::fromMovement(...), $found);
    }

    /**
     * Keeps the rows whose title or organization name has every word.
     *
     * @template TBuilder of Builder<covariant Model>
     *
     * @param  TBuilder  $query  The query.
     * @param  list<string>  $words  The words of the query.
     * @return TBuilder
     */
    private function matching(Builder $query, array $words): Builder
    {
        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '\\%_').'%';
            $query->where(static fn (Builder $query): Builder => $query
                ->where('title', 'like', $like)
                ->orWhereHas('organization', static fn (Builder $organization): Builder => $organization->where('name', 'like', $like)));
        }

        return $query;
    }

    /**
     * The words of a query, without blanks.
     *
     * @param  string  $query  What the person wrote.
     * @return list<string>
     */
    private function words(string $query): array
    {
        return array_values(array_filter(preg_split('/\s+/u', trim($query)) ?: [], static fn (string $word): bool => $word !== ''));
    }

    /**
     * A query written as an amount the Italian way (`23,90`, `1.234,50`, `45`) in cents, otherwise null.
     *
     * @param  string  $query  What the person wrote.
     */
    private function cents(string $query): ?int
    {
        $query = trim($query);

        if (preg_match('/^(\d{1,3}(\.\d{3})+|\d+)(,\d{1,2})?$/', $query) !== 1) {
            return null;
        }

        return (int) round((float) str_replace(',', '.', str_replace('.', '', $query)) * 100);
    }
}
