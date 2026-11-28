<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\DecodesAnswer;
use App\Ai\Agents\Concerns\HasReaderOptions;
use App\Support\Reading\BankFilterSuggestion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * The model that proposes a bank filter from the texts of the transactions it must take and of the ones it must
 * leave: the words, and when the names of the family are given a title, a category and an organization. It carries
 * the system text, the request and the reading of the answer; whether the words take the right transactions, and
 * whether the names are of the family, is checked by `SuggestBankFilter`.
 */
#[Timeout(30)]
final class BankFilterAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use DecodesAnswer;
    use HasReaderOptions;
    use Promptable;

    /** What the model is for and how the filter is written. */
    public const string SYSTEM = <<<'TXT'
You write a filter that recognises the bank transactions of one thing a family pays or receives (a bill, a rent, a subscription, a shop, a cashback), from the texts of some transactions it must take and of some it must leave out.

The filter is a list of words: a transaction is taken when its text, in upper case and without accents, has every word of contains and none of excludes.

Rules:
- contains: few words, in upper case, that stay the same from one transaction of the same thing to the next: who gets or gives the money, and the kind of operation when it helps. Every transaction to take must have all of them.
- IBAN, IMPORTO, CARTA, NUMERO and PERSONA in the texts stand for hidden data (an account number, an amount, a card number, another long number, a person's name): never use them.
- Leave out invoice numbers, dates, amounts, codes that change every time and card numbers. Words of one or two letters do not count.
- A mandate (MAN.…) or a creditor code (CID.…) of a direct debit is a good word on its own.
- excludes: words that only the transactions to leave out have; an empty list when contains already leaves them out.
- The filter must take every transaction to take and none of the transactions to leave out.
- When the request lists categories, organizations and planned payments of the family: title is a short title (2 to 5 words) for the expense or income, in the language of the request, without the date; category is one of the categories listed, written exactly as listed, or null when none fits; organization is the organization listed that gets or gives the money, written exactly as listed, or the name of who gets or gives it as the texts write it, in normal case, when it is not listed, or null when the texts do not say. The planned payments only say what the family already pays regularly.
- When the request lists no names: title, category and organization are null.
- explanation: one simple sentence, in the language of the request, that says what the filter takes.
TXT;

    /** The most words kept in a list. */
    private const int MAX_WORDS = 8;

    /** The longest word or phrase kept. */
    private const int MAX_WORD = 60;

    /** The longest title, name or explanation kept. */
    private const array MAX_TEXT = ['title' => 80, 'name' => 120, 'explanation' => 300];

    /**
     * @param  string  $model  the model asked, whose options depend on it
     */
    public function __construct(private readonly string $model) {}

    /**
     * What the model is for and how the filter is written.
     */
    public function instructions(): string
    {
        return self::SYSTEM;
    }

    /**
     * The schema of the answer: the two lists of words and the explanation, and the title, category and organization,
     * which can be null.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contains' => $schema->array()->items($schema->string())->required(),
            'excludes' => $schema->array()->items($schema->string())->required(),
            'title' => $schema->string()->nullable()->required(),
            'category' => $schema->string()->nullable()->required(),
            'organization' => $schema->string()->nullable()->required(),
            'explanation' => $schema->string()->required(),
        ];
    }

    /**
     * The text that goes to the model: the texts to take and to leave, one a line, the names of the family when they
     * are given, and the language of the answer. Nothing else: no amount, no account, no name of a person.
     *
     * @param  list<string>  $keep  the texts of the transactions to take, already normalised
     * @param  list<string>  $drop  the texts of the transactions to leave, already normalised
     * @param  array{categories: list<string>, organizations: list<string>, planned: list<string>}  $names  the names of the family, empty lists when only the words are asked
     * @param  string  $locale  the language of the title and the explanation (`it` or `en`)
     */
    public static function request(array $keep, array $drop, array $names, string $locale): string
    {
        $lines = ['Transactions to take:', ...self::items($keep), 'Transactions to leave out:', ...self::items($drop)];

        if ($names['categories'] !== [] || $names['organizations'] !== [] || $names['planned'] !== []) {
            $lines = [
                ...$lines,
                'Categories of the family:', ...self::items($names['categories']),
                'Organizations of the family:', ...self::items($names['organizations']),
                'Planned payments of the family:', ...self::items($names['planned']),
            ];
        }

        $lines[] = 'Language: '.($locale === 'it' ? 'Italian' : 'English');

        return implode("\n", $lines);
    }

    /**
     * The answer of the model, or null when it has no list of words to take.
     *
     * @param  array<array-key, mixed>|string|null  $answer  the structured answer, or its text
     */
    public static function parse(array|string|null $answer): ?BankFilterSuggestion
    {
        $answer = self::decodeAnswer($answer);

        if (! is_array($answer['contains'] ?? null)) {
            return null;
        }

        return new BankFilterSuggestion(
            contains: self::words($answer['contains']),
            excludes: is_array($answer['excludes'] ?? null) ? self::words($answer['excludes']) : [],
            title: self::text($answer['title'] ?? null, self::MAX_TEXT['title']),
            category: self::text($answer['category'] ?? null, self::MAX_TEXT['name']),
            organization: self::text($answer['organization'] ?? null, self::MAX_TEXT['name']),
            explanation: self::text($answer['explanation'] ?? null, self::MAX_TEXT['explanation']) ?? '',
        );
    }

    /**
     * The lines of a list, or a line that says it is empty.
     *
     * @param  list<string>  $values  the values
     * @return list<string>
     */
    private static function items(array $values): array
    {
        return $values === [] ? ['- none'] : array_map(static fn (string $value): string => '- '.$value, $values);
    }

    /**
     * The words of a list, trimmed, without blanks and repeats, each and the list cut to their limits.
     *
     * @param  array<array-key, mixed>  $values  what the model wrote
     * @return list<string>
     */
    private static function words(array $values): array
    {
        $words = [];

        foreach ($values as $value) {
            $word = is_string($value) ? trim(mb_substr(trim($value), 0, self::MAX_WORD)) : '';

            if ($word !== '' && ! in_array($word, $words, true)) {
                $words[] = $word;
            }
        }

        return array_slice($words, 0, self::MAX_WORDS);
    }

    /**
     * A text trimmed and cut to its limit, or null when it is empty or not a text.
     *
     * @param  mixed  $value  what the model wrote
     * @param  int  $max  the longest text kept
     */
    private static function text(mixed $value, int $max): ?string
    {
        $text = is_string($value) ? trim(mb_substr(trim($value), 0, $max)) : '';

        return $text === '' ? null : $text;
    }
}
