<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Data\Search\SearchResultData;
use App\Enums\SearchGroup;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\CardSummaries;
use App\Services\Deadlines;
use App\Support\Deadline;
use App\Support\SearchTerms;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The search over the deadlines: the next unpaid, not skipped due date (late ones included) of each planned
 * expense and planned income of the family, found by its title, the organization, the title of the card it
 * belongs to, its amount, or the day the due date falls on. A planned item without an unpaid due date in the
 * next year (finished, or all paid) is not a result.
 *
 * The title of a card cannot be looked up in SQL, so it is read in PHP: the planned items of the family that
 * have a card are few, and for each word the ids whose card title contains it join the SQL conditions as an
 * alternative of that word. The day of a due date is computed in PHP too, the same way.
 */
final readonly class DueResults implements GroupResults
{
    /** The columns every word is looked up in. */
    private const array COLUMNS = ['title', 'organization.name'];

    public function __construct(private Deadlines $deadlines, private CardSummaries $summaries) {}

    /**
     * {@inheritDoc}
     */
    public function group(): SearchGroup
    {
        return SearchGroup::Dues;
    }

    /**
     * {@inheritDoc}
     */
    public function count(SearchTerms $terms): int
    {
        return count($this->matches($terms));
    }

    /**
     * {@inheritDoc}
     *
     * Ordered by the date of the due date, the limit comes after.
     */
    public function results(SearchTerms $terms, int $limit): array
    {
        return array_map(
            fn (Deadline $deadline): SearchResultData => $this->result($deadline),
            array_slice($this->matches($terms), 0, max(0, $limit)),
        );
    }

    /**
     * The first open due date of every planned item where every word matches, in order of date.
     *
     * @param  SearchTerms  $terms  what the person typed
     * @return list<Deadline>
     */
    private function matches(SearchTerms $terms): array
    {
        $today = CarbonImmutable::today();

        // Each word may also be the title of a card or a day of a due date: the ids are looked up once per word
        $labelled = $this->labelled($terms->words);
        $onDay = [];
        $dates = array_filter(array_map(static fn (string $word): ?CarbonImmutable => SearchTerms::fromQuery($word)->date, $terms->words));

        if ($dates !== []) {
            $first = $this->firstOpen([...PlannedExpense::query()->get(), ...PlannedIncome::query()->get()], $today);

            foreach ($terms->words as $word) {
                $date = SearchTerms::fromQuery($word)->date;

                if ($date !== null) {
                    $onDay[$word] = array_filter($first, static fn (Deadline $d): bool => $d->occurrence->date->isSameDay($date));
                }
            }
        }

        $expenses = $terms->whereWords(
            PlannedExpense::with('organization'),
            self::COLUMNS,
            fn (Builder $group, string $word) => $this->alternatives($group, $word, 'expense', $labelled, $onDay),
        )->get();
        $incomes = $terms->whereWords(
            PlannedIncome::with('organization'),
            self::COLUMNS,
            fn (Builder $group, string $word) => $this->alternatives($group, $word, 'income', $labelled, $onDay),
        )->get();
        $planned = [...$expenses->all(), ...$incomes->all()];

        $found = array_values($this->firstOpen($planned, $today));
        usort($found, static fn (Deadline $a, Deadline $b): int => $a->occurrence->date <=> $b->occurrence->date);

        return $found;
    }

    /**
     * What else a word can be besides text in the title or the organization: the amount of the planned item, the
     * title of its card, or the day of its first open due date. It is an alternative of its own word only.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $group  the OR group of the word
     * @param  string  $word  the word
     * @param  string  $kind  `expense` or `income`, the kind of planned item looked up
     * @param  array<string, list<string>>  $labelled  by word, the keys (`expense-3`) whose card title contains it
     * @param  array<string, array<string, Deadline>>  $onDay  by word that is a day, the open due dates falling on it
     */
    private function alternatives(Builder $group, string $word, string $kind, array $labelled, array $onDay): void
    {
        $read = SearchTerms::fromQuery($word);

        if ($read->amount !== null) {
            $group->orWhere('amount', $read->amount);
        }

        $keys = [...($labelled[$word] ?? []), ...array_keys($onDay[$word] ?? [])];
        $ids = [];

        foreach ($keys as $key) {
            if (str_starts_with($key, $kind.'-')) {
                $ids[] = (int) substr($key, strlen($kind) + 1);
            }
        }

        if ($ids !== []) {
            $group->orWhereIn('id', $ids);
        }
    }

    /**
     * The keys (`expense-3`) of the planned items whose card has a title containing each word, ignoring case
     * and accents.
     *
     * @param  list<string>  $words  the words typed
     * @return array<string, list<string>>
     */
    private function labelled(array $words): array
    {
        $cards = PlannedExpense::query()->whereNotNull('card_type')->with(['card' => $this->deadlines->withCardRelations(...)])->get();
        $labels = [];

        foreach ($cards as $item) {
            if ($item->card instanceof Model) {
                $labels['expense-'.$item->id] = $this->plain($this->summaries->label($item->card));
            }
        }

        foreach (PlannedIncome::query()->whereNotNull('employment_id')->with('employment')->get() as $item) {
            if ($item->employment instanceof Model) {
                $labels['income-'.$item->id] = $this->plain($this->summaries->label($item->employment));
            }
        }

        $found = [];

        foreach ($words as $word) {
            $needle = $this->plain($word);
            $found[$word] = array_keys(array_filter($labels, static fn (string $label): bool => str_contains($label, $needle)));
        }

        return $found;
    }

    /**
     * The first open (not paid, not skipped) due date of each planned item, up to a year from today.
     *
     * @param  iterable<PlannedExpense|PlannedIncome>  $planned  the planned items
     * @param  CarbonImmutable  $today  today
     * @return array<string, Deadline> by key (`expense-3`)
     */
    private function firstOpen(iterable $planned, CarbonImmutable $today): array
    {
        $first = [];

        foreach ($this->deadlines->unpaidUntil($planned, $today->addYear(), $today) as $deadline) {
            $key = $deadline->kind().'-'.$deadline->planned->id;
            $first[$key] ??= $deadline;
        }

        return $first;
    }

    /**
     * The result row of a deadline: card title or organization as subtitle, the area of the card when there is one.
     *
     * @param  Deadline  $deadline  the first open due date of a planned item
     */
    private function result(Deadline $deadline): SearchResultData
    {
        $planned = $deadline->planned;
        $card = $planned instanceof PlannedExpense ? $planned->card : $planned->employment;
        $type = $card instanceof Model ? $this->cardType($card) : null;

        return new SearchResultData(
            key: 'due-'.$deadline->kind().'-'.$planned->id,
            group: SearchGroup::Dues,
            title: $planned->displayTitle(),
            subtitle: $card instanceof Model ? $this->summaries->label($card) : $planned->organization?->name,
            date: null,
            amount: $deadline->occurrence->amount,
            href: route('budget.deadlines.show', [
                'kind' => $deadline->kind(),
                'id' => $planned->id,
                'date' => $deadline->occurrence->originalDate->toDateString(),
            ]),
            area: $type?->area()->value,
            icon: $type === null ? 'calendar-clock' : null,
            tone: $type === null ? ($deadline->kind() === 'expense' ? 'expenses' : 'income') : null,
            flag: null,
            due_in: $deadline->occurrence->date->toDateString(),
        );
    }

    /**
     * The card type of a card, or null when the record is not a card.
     *
     * @param  Model  $card  the record
     */
    private function cardType(Model $card): ?CardType
    {
        return array_find(CardTypes::all(), static fn (CardType $type): bool => $card instanceof ($type->model()));
    }

    /**
     * Text without case and accents, to compare titles.
     *
     * @param  string  $text  the text
     */
    private function plain(string $text): string
    {
        return Str::of($text)->ascii()->lower()->toString();
    }
}
