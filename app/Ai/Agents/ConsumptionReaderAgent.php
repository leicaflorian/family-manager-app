<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasReaderOptions;
use App\Data\Reading\ConsumptionReading;
use App\Data\Reading\SupplyConsumptionReading;
use App\Enums\ConsumptionUnit;
use App\Enums\UtilityType;
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
 * `ReadingPrompt`. A bill of two or more supplies (electricity and gas) also lists each supply with its own
 * consumption, in the same call. The request gives up after 60 seconds.
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

    /** When and how the model lists the supplies of a bill that has more than one, and what the fields of the document hold then. */
    private const string SUPPLIES_RULE = ReadingPrompt::SUPPLIES_LIST.'; period_start, period_end and the consumption_* fields of the document are those of the first supply.';

    /** What goes to the model with the file. */
    public const string REQUEST = 'Read the consumption of this document.';

    /** The fields of the answer that are dates (`YYYY-MM-DD`). */
    private const array DATES = ['period_start', 'period_end'];

    /** The fields of the answer that are numbers. */
    private const array NUMBERS = ['consumption_quantity', 'total_amount', 'amount', 'consumption_energy_amount', 'consumption_f1', 'consumption_f2', 'consumption_f3'];

    /** The fields of the answer that are yes or no, besides `is_bill`. */
    private const array FLAGS = ['consumption_estimated', 'consumption_adjusted'];

    /** The fields of the answer about the whole bill, besides `is_bill` and `supplies`. */
    private const array FIELDS = ['period_start', 'period_end', 'consumption_quantity', 'consumption_unit', 'total_amount', ...self::FLAGS, 'consumption_energy_amount', 'consumption_f1', 'consumption_f2', 'consumption_f3'];

    /** The fields of each supply, those of the full reading. */
    private const array SUPPLY_FIELDS = ['utility_type', 'supply_point_code', 'amount', 'period_start', 'period_end', 'consumption_quantity', 'consumption_unit', ...self::FLAGS, 'consumption_energy_amount', 'consumption_f1', 'consumption_f2', 'consumption_f3'];

    /**
     * @param  string  $model  the model asked, whose options depend on it
     */
    public function __construct(private readonly string $model) {}

    /**
     * What the model is for, with the rules of the period and of the consumption of the full reading.
     */
    public function instructions(): string
    {
        return self::INTRO."\n".ReadingPrompt::PERIOD_RULE."\n".ReadingPrompt::CONSUMPTION_RULE."\n".self::SUPPLIES_RULE;
    }

    /**
     * The schema of the answer: every field required; `is_bill` is always answered, `supplies` is a list (empty for a
     * bill of one supply), the others are null when the paper does not say. Each supply has its fields required and
     * nullable, with the same types.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = ['is_bill' => $schema->boolean()->required(), ...$this->fields($schema, self::FIELDS)];

        $properties['supplies'] = $schema->array()
            ->items($schema->object($this->fields($schema, self::SUPPLY_FIELDS))->withoutAdditionalProperties())
            ->required();

        return $properties;
    }

    /**
     * The fields given, each of its type, nullable and required.
     *
     * @param  JsonSchema  $schema  the factory of the types
     * @param  list<string>  $fields  the fields
     * @return array<string, Type>
     */
    private function fields(JsonSchema $schema, array $fields): array
    {
        $properties = [];

        foreach ($fields as $field) {
            $type = match (true) {
                in_array($field, self::DATES, true) => $schema->string()->format('date'),
                in_array($field, self::NUMBERS, true) => $schema->number(),
                in_array($field, self::FLAGS, true) => $schema->boolean(),
                // A nullable enum lists null too: strict validators check `enum` as well as `type`.
                $field === 'consumption_unit' => $schema->string()->enum([...array_column(ConsumptionUnit::cases(), 'value'), null]),
                $field === 'utility_type' => $schema->string()->enum([...array_column(UtilityType::measured(), 'value'), null]),
                default => $schema->string(),
            };

            $properties[$field] = $type->nullable()->required();
        }

        return $properties;
    }

    /**
     * The reading from the answer of the model. A value of the wrong type becomes null; the unit and the period are
     * checked later by `ConsumptionCheck`. With two or more supplies the flat fields are rewritten from the first.
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

        $supplies = self::supplies($data['supplies'] ?? null);

        if ($supplies === []) {
            return self::reading($data, $data['is_bill'], self::number($data['total_amount'] ?? null));
        }

        // As in the full reading, the flat fields of a bill of several supplies are those of the first
        $first = $supplies[0]->reading;

        return new ConsumptionReading(
            isBill: $data['is_bill'],
            periodStart: $first->periodStart,
            periodEnd: $first->periodEnd,
            quantity: $first->quantity,
            unit: $first->unit,
            totalAmount: self::number($data['total_amount'] ?? null),
            estimated: $first->estimated,
            adjusted: $first->adjusted,
            energyAmount: $first->energyAmount,
            f1: $first->f1,
            f2: $first->f2,
            f3: $first->f3,
            supplies: $supplies,
        );
    }

    /**
     * The supplies of the answer: only objects, without those that have neither a code nor an amount; none when fewer
     * than two are left (a bill of one supply).
     *
     * @param  mixed  $value  the `supplies` of the answer
     * @return list<SupplyConsumptionReading>
     */
    private static function supplies(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $supplies = [];

        foreach ($value as $item) {
            if (! is_array($item) || array_is_list($item)) {
                continue;
            }

            $code = self::text($item['supply_point_code'] ?? null);
            $amount = self::number($item['amount'] ?? null);

            if ($code === null && $amount === null) {
                continue;
            }

            $type = self::text($item['utility_type'] ?? null);
            // Out of the choices the unit cannot say which supply it is: null, as the type
            $item['consumption_unit'] = ConsumptionUnit::tryFrom((string) self::text($item['consumption_unit'] ?? null))?->value;

            $supplies[] = new SupplyConsumptionReading(
                utilityType: in_array($type, array_column(UtilityType::measured(), 'value'), true) ? $type : null,
                supplyPointCode: $code,
                reading: self::reading($item, true, $amount),
            );
        }

        return count($supplies) >= 2 ? $supplies : [];
    }

    /**
     * The consumption fields of an object of the answer (the bill, or one of its supplies).
     *
     * @param  array<array-key, mixed>  $data  the object
     * @param  bool  $isBill  whether the paper is a bill
     * @param  float|null  $total  the total: of the bill, or the amount of the supply
     */
    private static function reading(array $data, bool $isBill, ?float $total): ConsumptionReading
    {
        return new ConsumptionReading(
            isBill: $isBill,
            periodStart: self::text($data['period_start'] ?? null),
            periodEnd: self::text($data['period_end'] ?? null),
            quantity: self::number($data['consumption_quantity'] ?? null),
            unit: self::text($data['consumption_unit'] ?? null),
            totalAmount: $total,
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
