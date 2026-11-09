<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What a person typed in the global search, read once: the clean text, its words, and the amount or the date
 * it may stand for. The searches of the groups build their queries from it.
 */
final readonly class SearchTerms
{
    /** The longest query that is read; the rest is cut. */
    private const int MAX_LENGTH = 100;

    /**
     * @param  string  $query  the clean text: no spaces at the sides, one space between words, at most 100 characters
     * @param  list<string>  $words  the words of the text
     * @param  string|null  $amount  the text as an amount with the point, for example `84.20`, or null when it is not a number
     * @param  CarbonImmutable|null  $date  the day when the text is a day and month (and year), or null
     */
    private function __construct(
        public string $query,
        public array $words,
        public ?string $amount,
        public ?CarbonImmutable $date,
    ) {}

    /**
     * Reads what was typed.
     *
     * @param  string  $query  the raw text of the search box
     */
    public static function fromQuery(string $query): self
    {
        $clean = trim(mb_substr(trim((string) preg_replace('/\s+/u', ' ', $query)), 0, self::MAX_LENGTH));
        $words = $clean === '' ? [] : explode(' ', $clean);

        return new self($clean, $words, self::readAmount($clean), self::readDate($clean));
    }

    /**
     * Whether the text is long enough to search: at least two characters that are not spaces.
     */
    public function isSearchable(): bool
    {
        return mb_strlen(str_replace(' ', '', $this->query)) >= 2;
    }

    /**
     * The `LIKE` pattern that contains a word, with the wildcards of the word escaped.
     *
     * @param  string  $word  the word to look for
     */
    public static function like(string $word): string
    {
        return '%'.addcslashes($word, '%_\\').'%';
    }

    /**
     * Keeps the rows where every word is in at least one of the columns. A column can be `relation.column`,
     * which looks in the column of the related row. A search can add its own alternatives for a word (the
     * date or the amount a word stands for, a JSON column) with `$alternatives`: it is called for each word with
     * the group where the word's `orWhere` conditions go.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  the query to narrow
     * @param  list<string>  $columns  the columns to look in
     * @param  Closure(Builder<TModel>, string): void|null  $alternatives  adds `orWhere` conditions for one word
     * @return Builder<TModel>
     */
    public function whereWords(Builder $query, array $columns, ?Closure $alternatives = null): Builder
    {
        foreach ($this->words as $word) {
            $like = self::like($word);

            $query->where(function (Builder $group) use ($columns, $like, $word, $alternatives): void {
                foreach ($columns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $field] = explode('.', $column, 2);
                        $group->orWhereHas($relation, fn (Builder $related): Builder => $related->whereLike($field, $like));
                    } else {
                        $group->orWhereLike($column, $like);
                    }
                }

                if ($alternatives instanceof Closure) {
                    $alternatives($group, $word);
                }
            });
        }

        return $query;
    }

    /**
     * The text as an amount: `84,20`, `84.20`, `1.488,79` or `84`, always with two decimals and the point.
     */
    private static function readAmount(string $text): ?string
    {
        if (preg_match('/^\d+(?:[.,]\d{1,2})?$/', $text) === 1) {
            return number_format((float) str_replace(',', '.', $text), 2, '.', '');
        }

        if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/', $text) === 1) {
            return number_format((float) str_replace(['.', ','], ['', '.'], $text), 2, '.', '');
        }

        return null;
    }

    /**
     * The text as a day, `15/10/2026`, or `15/10` in the current year; null when it is not a real day.
     */
    private static function readDate(string $text): ?CarbonImmutable
    {
        if (preg_match('#^(\d{1,2})/(\d{1,2})(?:/(\d{4}))?$#', $text, $match) !== 1) {
            return null;
        }

        $year = isset($match[3]) ? (int) $match[3] : CarbonImmutable::now()->year;

        if (! checkdate((int) $match[2], (int) $match[1], $year)) {
            return null;
        }

        return CarbonImmutable::create($year, (int) $match[2], (int) $match[1])?->startOfDay();
    }
}
