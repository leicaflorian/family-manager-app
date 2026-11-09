<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\Search\SearchResultData;
use App\Enums\SearchGroup;
use App\Models\Expense;
use App\Models\Income;
use App\Support\SearchTerms;
use Illuminate\Database\Eloquent\Builder;

/**
 * The search over the expenses and the incomes of the family, together: by title, notes, organization and person.
 * A word that is an amount (`84,20`) also finds the movements of that amount, and a word that is a day
 * (`15/10/2026`) those of that day.
 */
final readonly class MovementResults implements GroupResults
{
    /** The columns every word is looked up in. */
    private const array COLUMNS = ['title', 'notes', 'organization.name', 'person.first_name'];

    /**
     * {@inheritDoc}
     */
    public function group(): SearchGroup
    {
        return SearchGroup::Movements;
    }

    /**
     * {@inheritDoc}
     *
     * The sum of the two counts: an expense and an income are never the same row.
     */
    public function count(SearchTerms $terms): int
    {
        return $this->expenses($terms)->count() + $this->incomes($terms)->count();
    }

    /**
     * {@inheritDoc}
     *
     * Each kind is read with the limit, then the two lists are merged by date (most recent first) and cut.
     */
    public function results(SearchTerms $terms, int $limit): array
    {
        $limit = max(0, $limit);

        $expenses = $this->expenses($terms)->orderByDesc('date')->orderByDesc('id')->limit($limit)->get();
        $incomes = $this->incomes($terms)->orderByDesc('date')->orderByDesc('id')->limit($limit)->get();

        $rows = [
            ...$expenses->map(fn (Expense $expense): SearchResultData => $this->expense($expense))->all(),
            ...$incomes->map(fn (Income $income): SearchResultData => $this->income($income))->all(),
        ];

        // A stable sort: on the same day an expense stays before an income, as they were read.
        usort($rows, static fn (SearchResultData $a, SearchResultData $b): int => strcmp((string) $b->date, (string) $a->date));

        return array_slice($rows, 0, $limit);
    }

    /**
     * The result row of an expense.
     *
     * @param  Expense  $expense  the expense
     */
    private function expense(Expense $expense): SearchResultData
    {
        return new SearchResultData(
            key: 'expense-'.$expense->id,
            group: SearchGroup::Movements,
            title: $expense->title,
            subtitle: (string) __('Expense'),
            date: $expense->date?->toDateString(),
            amount: (float) $expense->amount,
            href: route('budget.movements.show', ['kind' => 'expense', 'id' => $expense->id]),
            area: null,
            icon: 'receipt',
            tone: 'expenses',
            flag: null,
            due_in: null,
        );
    }

    /**
     * The result row of an income; one without a title is named by its type (for example *Salary*).
     *
     * @param  Income  $income  the income
     */
    private function income(Income $income): SearchResultData
    {
        return new SearchResultData(
            key: 'income-'.$income->id,
            group: SearchGroup::Movements,
            title: $income->title ?? $income->type->label(),
            subtitle: (string) __('Income entry'),
            date: $income->date->toDateString(),
            amount: $income->amount === null ? null : (float) $income->amount,
            href: route('budget.movements.show', ['kind' => 'income', 'id' => $income->id]),
            area: null,
            icon: 'arrow-down-left',
            tone: 'income',
            flag: null,
            due_in: null,
        );
    }

    /**
     * The expenses where every word matches, see {@see self::alternatives()}.
     *
     * @param  SearchTerms  $terms  what the person typed
     * @return Builder<Expense>
     */
    private function expenses(SearchTerms $terms): Builder
    {
        return $terms->whereWords(Expense::with(['organization', 'person']), self::COLUMNS, $this->alternatives(...));
    }

    /**
     * The incomes where every word matches, see {@see self::alternatives()}.
     *
     * @param  SearchTerms  $terms  what the person typed
     * @return Builder<Income>
     */
    private function incomes(SearchTerms $terms): Builder
    {
        return $terms->whereWords(Income::with(['organization', 'person']), self::COLUMNS, $this->alternatives(...));
    }

    /**
     * What else a word can be besides text: an amount (`84,20`) or a day (`15/10/2026`) matches the amount or the
     * date of the movement. It is an alternative of its own word only: the other words are still looked up as
     * text, so «gas 84,20» finds the gas expense of 84,20 €.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $group  the OR group of the word
     * @param  string  $word  the word
     */
    private function alternatives(Builder $group, string $word): void
    {
        $read = SearchTerms::fromQuery($word);

        if ($read->amount !== null) {
            $group->orWhere('amount', $read->amount);
        }

        if ($read->date !== null) {
            $group->orWhereDate('date', $read->date);
        }
    }
}
