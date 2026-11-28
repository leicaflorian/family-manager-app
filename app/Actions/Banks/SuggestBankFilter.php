<?php

declare(strict_types=1);

namespace App\Actions\Banks;

use App\Contracts\DocumentReader;
use App\Exceptions\Reading\ReadingFailed;
use App\Models\BankTransaction;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Services\Reading\ReadingAvailability;
use App\Support\Banks\BankFilterCriteria;
use App\Support\Banks\BankFilterMatch;
use App\Support\Banks\BankMatch;
use App\Support\Banks\BankText;
use App\Support\Banks\BankTextMask;
use App\Support\Reading\BankFilterSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Asks the AI for a bank filter (*Scrivila con l'AI*, *Migliora il filtro*, *Non è di questo gruppo*, *Crea il filtro*)
 * from the transactions it must take and the ones it must leave. The words of the model are kept only when they are
 * safe, take every transaction to keep and none to drop; otherwise the words of the first one take their place. A
 * category or an organization counts only when the family has it; an organization it does not have comes back as a
 * new name to confirm.
 */
final readonly class SuggestBankFilter
{
    /** Only the words: *Scrivila con l'AI*, *Migliora il filtro*, *Non è di questo gruppo*. */
    public const string WORDS = 'words';

    /** The words and a title, a category and an organization: *Crea il filtro* on a group without a proposal. */
    public const string FILL = 'fill';

    /** The most texts of each kind sent to the model; every one is still checked. */
    private const int MAX_TEXTS = 20;

    /** The most names of each kind sent to the model. */
    private const int MAX_NAMES = 200;

    /**
     * @param  DocumentReader  $reader  the model that proposes the filter
     * @param  ReadingAvailability  $availability  says whether a reading can be asked now
     */
    public function __construct(private DocumentReader $reader, private ReadingAvailability $availability) {}

    /**
     * The filter for the transactions. Only their normalised texts go to the model, and in the fill mode the names of
     * the categories, organizations and planned items of the family: no amount, no IBAN, no name of a person.
     *
     * @param  list<BankTransaction>  $keep  the transactions the filter must take, at least one; the first is the example of the fallback
     * @param  list<BankTransaction>  $drop  the transactions it must leave
     * @param  string  $mode  `words` or `fill`
     * @param  string  $locale  the language of the title and the explanation (`it` or `en`)
     *
     * @throws ValidationException when the reading is off or the month is used up
     * @throws ReadingFailed when the service gives no answer at all (an answer that comes but is unusable gives the words of the first transaction)
     * @throws InvalidArgumentException when there is no transaction to keep
     */
    public function handle(array $keep, array $drop, string $mode, string $locale): BankFilterSuggestion
    {
        if ($keep === []) {
            throw new InvalidArgumentException('A filter is suggested for at least one transaction.');
        }

        $this->availability->ensure($locale, 'keep');

        $fill = $mode === self::FILL;
        $names = [
            'categories' => $fill ? $this->names(ExpenseCategory::class) : [],
            'organizations' => $fill ? $this->names(Organization::class) : [],
            'planned' => $fill ? $this->planned() : [],
        ];
        $people = BankTextMask::familyNames();

        try {
            $suggestion = $this->reader->bankFilter($this->texts($keep, $people), $this->texts($drop, $people), $names, $locale);
        } catch (ReadingFailed $failed) {
            // the answer came (and was paid for) but cannot be used: a new call would pay twice
            return $failed->paid ? $this->fallback($keep[0], null, $locale) : throw $failed;
        }

        $suggestion = $this->normalized($suggestion);
        $words = $this->takesExactly($suggestion, $keep, $drop) ? $suggestion : $this->fallback($keep[0], $suggestion, $locale);

        if (! $fill) {
            return new BankFilterSuggestion($words->contains, $words->excludes, null, null, null, $words->explanation);
        }

        $category = $this->find(ExpenseCategory::class, $suggestion->category);
        $organization = $this->find(Organization::class, $suggestion->organization);

        return new BankFilterSuggestion(
            contains: $words->contains,
            excludes: $words->excludes,
            title: $suggestion->title,
            category: $category?->name,
            organization: $organization->name ?? $suggestion->organization,
            explanation: $words->explanation,
            categoryId: $category?->id,
            organizationId: $organization?->id,
        );
    }

    /**
     * The suggestion with its words as the filter compares them: normalised, without blanks and repeats.
     *
     * @param  BankFilterSuggestion  $suggestion  what the model proposed
     */
    private function normalized(BankFilterSuggestion $suggestion): BankFilterSuggestion
    {
        $clean = static fn (array $words): array => array_values(array_unique(array_filter(array_map(BankMatch::normalize(...), $words), static fn (string $word): bool => $word !== '')));

        return new BankFilterSuggestion($clean($suggestion->contains), $clean($suggestion->excludes), $suggestion->title, $suggestion->category, $suggestion->organization, $suggestion->explanation);
    }

    /**
     * Whether the words of the suggestion are safe, take every transaction to keep and none to drop.
     *
     * @param  BankFilterSuggestion  $suggestion  what the model proposed
     * @param  list<BankTransaction>  $keep  the transactions to take
     * @param  list<BankTransaction>  $drop  the transactions to leave
     */
    private function takesExactly(BankFilterSuggestion $suggestion, array $keep, array $drop): bool
    {
        $criteria = BankFilterCriteria::fromArray([
            'contains' => $suggestion->contains,
            'excludes' => $suggestion->excludes,
            'direction' => $keep[0]->kind(),
        ]);

        return $criteria->isSafe()
            && array_all($keep, static fn (BankTransaction $transaction): bool => BankFilterMatch::matches($criteria, $transaction))
            && ! array_any($drop, static fn (BankTransaction $transaction): bool => BankFilterMatch::matches($criteria, $transaction));
    }

    /**
     * The words the system proposes for the first transaction, with a sentence that lists them, in place of the words
     * of the model; its title and names, if any, stay as they are.
     *
     * @param  BankTransaction  $example  the first transaction to keep
     * @param  BankFilterSuggestion|null  $suggestion  what the model proposed, if it answered
     * @param  string  $locale  the language of the sentence
     */
    private function fallback(BankTransaction $example, ?BankFilterSuggestion $suggestion, string $locale): BankFilterSuggestion
    {
        $words = BankText::words($example->text);
        $explanation = $words === [] ? '' : __('Recognizes the transactions with these words: :words', ['words' => implode(' ', $words)], $locale);

        return new BankFilterSuggestion($words, [], $suggestion?->title, $suggestion?->category, $suggestion?->organization, $explanation);
    }

    /**
     * The normalised texts of the transactions as they may go to the model (IBANs, amounts and the names of the
     * family masked), without the empty ones, at most `MAX_TEXTS`.
     *
     * @param  list<BankTransaction>  $transactions  the transactions
     * @param  list<string>  $people  the names of the people of the family
     * @return list<string>
     */
    private function texts(array $transactions, array $people): array
    {
        $texts = array_filter(array_map(static fn (BankTransaction $transaction): string => BankTextMask::forModel(BankMatch::normalize($transaction->text), $people), $transactions), static fn (string $text): bool => $text !== '');

        return array_slice(array_values($texts), 0, self::MAX_TEXTS);
    }

    /**
     * The names of the categories or organizations of the family, without repeats, at most `MAX_NAMES`.
     *
     * @param  class-string<ExpenseCategory|Organization>  $model  the model
     * @return list<string>
     */
    private function names(string $model): array
    {
        /** @var Collection<int, string> $names */
        $names = $model::query()->orderBy('name')->orderBy('id')->limit(self::MAX_NAMES)->pluck('name');

        return $this->distinct($names);
    }

    /**
     * The names of the planned expenses and incomes of the family, without repeats.
     *
     * @return list<string>
     */
    private function planned(): array
    {
        $expenses = PlannedExpense::query()->orderBy('title')->limit(self::MAX_NAMES)->get()->map(static fn (PlannedExpense $planned): string => $planned->displayTitle());
        $incomes = PlannedIncome::query()->orderBy('title')->limit(self::MAX_NAMES)->get()->map(static fn (PlannedIncome $planned): string => $planned->displayTitle());

        return $this->distinct($expenses->concat($incomes));
    }

    /**
     * The names without repeats, in order.
     *
     * @param  Collection<array-key, string>  $names  the names
     * @return list<string>
     */
    private function distinct(Collection $names): array
    {
        return array_values($names->unique()->all());
    }

    /**
     * The category or organization of the family with the name the model wrote, whatever the case, among all of them
     * (not only the names sent); null when the family has none.
     *
     * @param  class-string<ExpenseCategory|Organization>  $model  the model
     * @param  string|null  $name  what the model wrote
     */
    private function find(string $model, ?string $name): ExpenseCategory|Organization|null
    {
        if ($name === null) {
            return null;
        }

        return $model::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->orderBy('id')->first();
    }
}
