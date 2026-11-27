<?php

declare(strict_types=1);

namespace App\Services\Banks;

use App\Data\BankPlannedChoiceData;
use App\Data\BankRecordedChoiceData;
use App\Enums\BankMatchSource;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Support\Banks\BankMatch;
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

    /** How many days around the transaction the empty search looks for a recorded movement of the same amount. */
    private const int RECENT_DAYS = 30;

    /** The shortest name of an organization that counts as found in a text (a shorter one is in everything). */
    private const int MIN_NAME = 3;

    public function __construct(private BankMatcher $matcher) {}

    /**
     * The two lists for a query.
     *
     * - `planned`: the planned items, ended ones too, whose title or organization has every word of the query, in order
     *   of title, each with the due date it would settle (`BankMatcher::deadlineAnywhere()`). With an empty query: the
     *   ones with a due date within reach of `BankMatcher::deadline()`.
     * - `recorded`: the expenses (or incomes) no other bank transaction has, by the words of the title, the name of the
     *   organization or, when the query is a number written the Italian way, the amount; the ones with the amount of the
     *   transaction first, then by the closest date. With an empty query: the same amount within 30 days of the transaction.
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
     * The search of *It is the commission of…*: only the expenses somebody recorded, even the ones a bank transaction
     * is linked to already (the debit of the commission is there), without the ones that have a commission from the
     * bank. With words: by title, organization or amount. Without: the expenses of the organizations whose name is in
     * the text of the commission, the newest first; `organization` is the name of the first of them.
     *
     * @param  BankTransaction  $fee  The commission.
     * @param  string  $query  What the person wrote.
     * @return array{planned: list<BankPlannedChoiceData>, recorded: list<BankRecordedChoiceData>, organization: string|null}
     */
    public function searchForFee(BankTransaction $fee, string $query): array
    {
        if (! $fee->booked_on instanceof CarbonImmutable || $fee->currency !== 'EUR' || $fee->kind() !== 'expense') {
            return ['planned' => [], 'recorded' => [], 'organization' => null];
        }

        $words = $this->words($query);

        if ($words !== []) {
            return ['planned' => [], 'recorded' => $this->recorded($fee, $words, $this->cents($query), fee: true), 'organization' => null];
        }

        $text = BankMatch::normalize($fee->text.' '.$fee->counterparty);
        $organizations = Organization::query()->orderBy('name')->get()->filter(static function (Organization $organization) use ($text): bool {
            $name = BankMatch::normalize($organization->name);

            return mb_strlen($name) >= self::MIN_NAME && str_contains($text, $name);
        })->values();

        $found = $organizations->isEmpty() ? [] : Expense::query()
            ->with('organization')
            ->whereIn('organization_id', $organizations->pluck('id'))
            ->whereNotIn('id', BankTransaction::query()->where('matched_by', BankMatchSource::Fee)->whereNotNull('expense_id')->select('expense_id'))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->all();

        return ['planned' => [], 'recorded' => array_values(array_map(BankRecordedChoiceData::fromMovement(...), $found)), 'organization' => $organizations->first()?->name];
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
     * @param  bool  $fee  The transaction is a commission: expenses already linked to a transaction are listed too, only those that have a commission are left out.
     * @return list<BankRecordedChoiceData>
     */
    private function recorded(BankTransaction $transaction, array $words, ?int $cents, bool $fee = false): array
    {
        $isExpense = $transaction->kind() === 'expense';
        $column = $isExpense ? 'expense_id' : 'income_id';
        $own = (int) round(abs((float) $transaction->amount) * 100);
        $day = $transaction->booked_on?->toDateString() ?? CarbonImmutable::today('Europe/Rome')->toDateString();
        $query = ($isExpense ? Expense::query() : Income::query())
            ->with('organization');

        if ($fee) {
            $query->whereNotIn('id', BankTransaction::query()->where('matched_by', BankMatchSource::Fee)->whereNotNull($column)->select($column));
        } else {
            $query->whereNotIn('id', BankTransaction::query()->whereNotNull($column)->select($column));
        }

        if ($words === []) {
            $around = $transaction->booked_on ?? CarbonImmutable::today('Europe/Rome');
            $query->whereBetween('date', [$around->subDays(self::RECENT_DAYS)->toDateString(), $around->addDays(self::RECENT_DAYS)->toDateString()])
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
            ->orderByRaw('round(amount * 100) = ? desc', [$fee ? -1 : $own])
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
