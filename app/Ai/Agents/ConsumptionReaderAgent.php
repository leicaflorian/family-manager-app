<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasReaderOptions;
use App\Data\Reading\ConsumptionReading;
use App\Enums\ConsumptionUnit;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use JsonException;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * The model that reads only the consumption of a bill already in the archive (*Read the bills*): whether the paper is
 * a bill of electricity, gas or water, then consumption, period and total, with the same rules as the full reading of
 * `ReadingPrompt`. The request gives up after 60 seconds.
 */
#[Timeout(60)]
final class ConsumptionReaderAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use HasReaderOptions;
    use Promptable;

    /** What the model is for, before the rules shared with the full reading. */
    private const string INTRO = <<<'TXT'
You read one Italian document of a household archive to take the consumption of a utility bill.

Rules:
- Copy values exactly as printed. Use null when a value is not on the document or does not apply; never guess.
- is_bill: true only when the document is a utility bill of electricity, gas or water; false for anything else (a contract, a letter, a payment receipt, a bill of phone, internet or waste tax). When is_bill is false every other field is null.
- Dates in YYYY-MM-DD.
- total_amount: the total to pay of the bill, in euro, as a number.
TXT;

    /** What goes to the model with the file. */
    public const string REQUEST = 'Read the consumption of this document.';

    /** The fields of the answer that are dates (`YYYY-MM-DD`). */
    private const array DATES = ['period_start', 'period_end'];

    /** The fields of the answer that are numbers. */
    private const array NUMBERS = ['consumption_quantity', 'total_amount', 'consumption_energy_amount', 'consumption_f1', 'consumption_f2', 'consumption_f3'];

    /** The fields of the answer that are yes or no, besides `is_bill`. */
    private const array FLAGS = ['consumption_estimated', 'consumption_adjusted'];

    /**
     * @param  string  $model  the model asked, whose options depend on it
     */
    public function __construct(private readonly string $model) {}

    /**
     * What the model is for, with the rules of the period and of the consumption of the full reading.
     */
    public function instructions(): string
    {
        return self::INTRO."\n".ReadingPrompt::PERIOD_RULE."\n".ReadingPrompt::CONSUMPTION_RULE;
    }

    /**
     * The schema of the answer: every field required; `is_bill` is always answered, the others are null when the paper
     * does not say.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = ['is_bill' => $schema->boolean()->required()];

        foreach (['period_start', 'period_end', 'consumption_quantity', 'consumption_unit', 'total_amount', ...self::FLAGS, 'consumption_energy_amount', 'consumption_f1', 'consumption_f2', 'consumption_f3'] as $field) {
            $type = match (true) {
                in_array($field, self::DATES, true) => $schema->string()->format('date'),
                in_array($field, self::NUMBERS, true) => $schema->number(),
                in_array($field, self::FLAGS, true) => $schema->boolean(),
                // A nullable enum lists null too: strict validators check `enum` as well as `type`.
                default => $schema->string()->enum([...array_column(ConsumptionUnit::cases(), 'value'), null]),
            };

            $properties[$field] = $type->nullable()->required();
        }

        return $properties;
    }

    /**
     * The reading from the answer of the model. A value of the wrong type becomes null; the unit and the period are
     * checked later by `ConsumptionCheck`.
     *
     * @param  array<array-key, mixed>|string|null  $answer  the structured answer, or its JSON text
     *
     * @throws ReadingFailed when the answer is empty, is not a JSON object or has no `is_bill` (the call is paid)
     */
    public static function parse(array|string|null $answer): ConsumptionReading
    {
        if (in_array($answer, [null, '', []], true)) {
            throw ReadingFailed::emptyAnswer();
        }

        $data = is_array($answer) ? $answer : self::decode($answer);

        if (! is_bool($data['is_bill'] ?? null)) {
            throw ReadingFailed::invalidAnswer();
        }

        return new ConsumptionReading(
            isBill: $data['is_bill'],
            periodStart: self::text($data['period_start'] ?? null),
            periodEnd: self::text($data['period_end'] ?? null),
            quantity: self::number($data['consumption_quantity'] ?? null),
            unit: self::text($data['consumption_unit'] ?? null),
            totalAmount: self::number($data['total_amount'] ?? null),
            estimated: is_bool($data['consumption_estimated'] ?? null) ? $data['consumption_estimated'] : null,
            adjusted: is_bool($data['consumption_adjusted'] ?? null) ? $data['consumption_adjusted'] : null,
            energyAmount: self::number($data['consumption_energy_amount'] ?? null),
            f1: self::number($data['consumption_f1'] ?? null),
            f2: self::number($data['consumption_f2'] ?? null),
            f3: self::number($data['consumption_f3'] ?? null),
        );
    }

    /**
     * The JSON object of the text.
     *
     * @return array<array-key, mixed>
     *
     * @throws ReadingFailed when the text is not a JSON object
     */
    private static function decode(string $text): array
    {
        try {
            $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw ReadingFailed::invalidAnswer($e);
        }

        return is_array($data) && ! array_is_list($data) ? $data : throw ReadingFailed::invalidAnswer();
    }

    /**
     * A text of the answer, or null when it is not one or is empty.
     *
     * @param  mixed  $value  the value of the answer
     */
    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * A finite number of the answer, or null.
     *
     * @param  mixed  $value  the value of the answer
     */
    private static function number(mixed $value): ?float
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value) ? (float) $value : null;
    }
}
