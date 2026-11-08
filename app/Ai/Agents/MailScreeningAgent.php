<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasReaderOptions;
use App\Data\Mail\MailScreeningAnswer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Throwable;

/**
 * The model that decides, from the sender and the subject of a mail only, whether it is worth reading. It carries
 * the system text, the request and the parsing of the answer, which count as unsure whenever they are not clear.
 */
#[Timeout(30)]
final class MailScreeningAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use HasReaderOptions;
    use Promptable;

    /** What the model is for and what each verdict means. */
    public const string SYSTEM = <<<'TXT'
You sort the incoming mail of a family archive. You see only the sender and the subject of a mail, never its body, and you decide whether the mail is worth reading because it carries something to file.

Answer:
- keep: the mail is likely to carry paperwork to file: a bill, an invoice, a deadline, a payment notice, a communication from a bank, an insurance company, a school, a health service or a public body.
- discard: the mail is advertising, a newsletter, an offer, a social network notification or a delivery notice.
- unsure: the sender and the subject are not enough to tell.

Rules:
- Judge only on what you see; never guess what the body says. When in doubt answer unsure, not discard.
- reason: one short sentence that says why (e.g. "gas bill", "commercial offer"), in the language the request asks for.
TXT;

    /** The verdicts the model can give. */
    public const array VERDICTS = ['keep', 'discard', 'unsure'];

    /** The longest reason kept. */
    private const int MAX_REASON = 200;

    /**
     * @param  string  $model  the model asked, whose options depend on it
     */
    public function __construct(private readonly string $model) {}

    /**
     * What the model is for and what each verdict means.
     */
    public function instructions(): string
    {
        return self::SYSTEM;
    }

    /**
     * The schema of the answer: a verdict from the list and a reason, both required.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'verdict' => $schema->string()->enum(self::VERDICTS)->required(),
            'reason' => $schema->string()->required(),
        ];
    }

    /**
     * The text that goes to the model: who wrote, what the subject says and the language of the reason.
     *
     * @param  string  $sender  the sender, as the mail shows it
     * @param  string  $subject  the subject, possibly empty
     * @param  string  $locale  the language of the reason (`it` or `en`)
     */
    public static function request(string $sender, string $subject, string $locale): string
    {
        $subject = trim($subject);
        $language = $locale === 'it' ? 'Italian' : 'English';

        return 'Sender: '.trim($sender)."\nSubject: ".($subject === '' ? 'senza oggetto' : $subject)."\nLanguage of the reason: {$language}";
    }

    /**
     * The answer of the model. Anything that is not a valid answer counts as unsure, because the next step is a
     * person's: a mail is never thrown away on a doubt.
     *
     * @param  array<array-key, mixed>|string|null  $answer  the structured answer, or its text
     */
    public static function parse(array|string|null $answer): MailScreeningAnswer
    {
        $unsure = new MailScreeningAnswer('unsure', null);

        if (is_string($answer)) {
            try {
                $decoded = json_decode($answer, true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return $unsure;
            }

            $answer = is_array($decoded) ? $decoded : null;
        }

        $verdict = $answer['verdict'] ?? null;

        if (! is_string($verdict) || ! in_array($verdict, self::VERDICTS, true)) {
            return $unsure;
        }

        $reason = is_string($answer['reason'] ?? null) ? mb_substr($answer['reason'], 0, self::MAX_REASON) : '';

        /** @var 'keep'|'discard'|'unsure' $verdict */
        return new MailScreeningAnswer($verdict, $reason === '' ? null : $reason);
    }
}
