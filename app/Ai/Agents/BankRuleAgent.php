<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasReaderOptions;
use App\Data\Banks\BankRuleSuggestion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Throwable;

/**
 * The model that writes the rule of a bank payment (*Come compare in banca*) from the texts of some of its
 * transactions. It carries the system text, the request and the reading of the answer; whether the rule is safe
 * and matches the examples is checked by `WriteBankMatch`.
 */
#[Timeout(30)]
final class BankRuleAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use HasReaderOptions;
    use Promptable;

    /** What the model is for and how the rule is written. */
    public const string SYSTEM = <<<'TXT'
You write the rule that recognises the bank transactions of one recurring payment (a bill, a rent, a subscription), from the texts of some of its past transactions.

The rule is a short regular expression in PCRE syntax, without delimiters and without options. It is matched, in upper case and without accents, against the text of the next transactions of the same payment.

Rules:
- Keep the words that stay the same from one payment to the next: who gets the money and the kind of operation.
- Leave out invoice numbers, dates, amounts, codes and card numbers.
- Join the words with .* and write them in upper case.
- Do not use ^ or $.
- The rule must match every example given, and must not match any transaction of something else.
- explanation: one simple sentence, in the language the request asks for, that says what the rule recognises. Never use the words "regular expression".
TXT;

    /** The longest explanation kept. */
    private const int MAX_EXPLANATION = 300;

    /**
     * @param  string  $model  the model asked, whose options depend on it
     */
    public function __construct(private readonly string $model) {}

    /**
     * What the model is for and how the rule is written.
     */
    public function instructions(): string
    {
        return self::SYSTEM;
    }

    /**
     * The schema of the answer: the rule and its explanation, both required.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema->string()->required(),
            'explanation' => $schema->string()->required(),
        ];
    }

    /**
     * The text that goes to the model: the texts of the transactions, one a line, and the language of the explanation.
     *
     * @param  list<string>  $examples  the texts of the transactions, already normalised
     * @param  string  $locale  the language of the explanation (`it` or `en`)
     */
    public static function request(array $examples, string $locale): string
    {
        $language = $locale === 'it' ? 'Italian' : 'English';

        return "Transactions of the same payment:\n".implode("\n", array_map(static fn (string $text): string => '- '.$text, $examples))."\nLanguage of the explanation: {$language}";
    }

    /**
     * The answer of the model, or null when it is not a rule with an explanation.
     *
     * @param  array<array-key, mixed>|string|null  $answer  the structured answer, or its text
     */
    public static function parse(array|string|null $answer): ?BankRuleSuggestion
    {
        if (is_string($answer)) {
            try {
                $decoded = json_decode($answer, true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return null;
            }

            $answer = is_array($decoded) ? $decoded : null;
        }

        $pattern = $answer['pattern'] ?? null;

        if (! is_string($pattern) || trim($pattern) === '') {
            return null;
        }

        $explanation = is_string($answer['explanation'] ?? null) ? mb_substr(trim($answer['explanation']), 0, self::MAX_EXPLANATION) : '';

        return new BankRuleSuggestion(trim($pattern), $explanation);
    }
}
