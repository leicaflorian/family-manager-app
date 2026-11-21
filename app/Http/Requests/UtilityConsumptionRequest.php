<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Consumption\ConsumptionValues;
use App\Enums\ConsumptionUnit;
use App\Models\Document;
use App\Models\Utility;
use App\Models\UtilityConsumption;
use App\Support\Reading\ConsumptionCheck;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A consumption written by hand, from the panel of the Consumption page: a new one of a utility
 * (`budget/consumption/{utility}/rows`) or the correction of a row (`budget/consumption/rows/{consumption}`).
 * The limits are those of a reading (`ConsumptionCheck`), except that a credit (a negative consumption) is allowed.
 */
final class UtilityConsumptionRequest extends FormRequest
{
    /** The highest amount of a bill, in euro. */
    private const int MAX_AMOUNT = 99_999_999;

    /** The utility the consumption belongs to, found in `prepareForValidation()`. */
    private ?Utility $resolvedUtility = null;

    /**
     * Finds the utility before the authorization and the rules, so a utility without consumption answers 404 whatever
     * the body says. A utility or a row of another family already answered 404 when the address was bound.
     *
     * @throws NotFoundHttpException if the utility has no consumption (internet, phone…)
     */
    protected function prepareForValidation(): void
    {
        $this->unit();
    }

    /**
     * Whether the person may add a consumption, or correct this one.
     */
    public function authorize(): bool
    {
        $row = $this->row();

        return $row instanceof UtilityConsumption
            ? $this->user()?->can('update', $row) ?? false
            : $this->user()?->can('create', UtilityConsumption::class) ?? false;
    }

    /**
     * The rules of the panel.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cap = ConsumptionCheck::cap($this->unit());

        return [
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start', $this->periodFits(...)],
            'quantity' => ['required', 'numeric', "between:-{$cap},{$cap}", $this->notZero(...)],
            'amount' => ['nullable', 'numeric', 'between:0,'.self::MAX_AMOUNT],
            'is_estimated' => ['sometimes', 'boolean'],
            'document_id' => $this->row() instanceof UtilityConsumption ? ['prohibited'] : ['nullable', 'integer', $this->billOfUtility(...)],
        ];
    }

    /**
     * The utility of the address, or of the row being corrected.
     *
     * @throws NotFoundHttpException if the address names neither
     */
    public function utility(): Utility
    {
        if ($this->resolvedUtility instanceof Utility) {
            return $this->resolvedUtility;
        }

        $utility = $this->route('utility');
        $row = $this->row();

        return $this->resolvedUtility = match (true) {
            $utility instanceof Utility => $utility,
            $row instanceof UtilityConsumption => $row->utility,
            default => throw new NotFoundHttpException,
        };
    }

    /**
     * The row being corrected, null for a new consumption.
     */
    public function row(): ?UtilityConsumption
    {
        $row = $this->route('consumption');

        return $row instanceof UtilityConsumption ? $row : null;
    }

    /**
     * The bill the consumption is written for («Write it» of *To check*), null for one added by hand.
     */
    public function document(): ?Document
    {
        $id = $this->validated('document_id');

        return is_numeric($id) ? $this->utility()->documents()->whereKey((int) $id)->first() : null;
    }

    /**
     * The figures to save. A correction keeps what the panel does not show (the adjustment, the energy part, the
     * bands) while the figures they belong to stay the same.
     */
    public function values(): ConsumptionValues
    {
        /** @var array{period_start: string, period_end: string, quantity: numeric-string|int|float, amount?: numeric-string|int|float|null, is_estimated?: bool|int|string} $data */
        $data = $this->validated();
        $quantity = (float) $data['quantity'];
        $amount = isset($data['amount']) ? (float) $data['amount'] : null;
        $row = $this->row();
        $sameQuantity = $row instanceof UtilityConsumption && (float) $row->quantity === $quantity;
        $sameAmount = $row instanceof UtilityConsumption && $row->amount !== null && (float) $row->amount === $amount;

        return new ConsumptionValues(
            periodStart: CarbonImmutable::createFromFormat('!Y-m-d', $data['period_start'], 'Europe/Rome') ?: throw new NotFoundHttpException,
            periodEnd: CarbonImmutable::createFromFormat('!Y-m-d', $data['period_end'], 'Europe/Rome') ?: throw new NotFoundHttpException,
            quantity: $quantity,
            amount: $amount,
            isEstimated: $this->boolean('is_estimated'),
            hasAdjustments: $row instanceof UtilityConsumption && $row->has_adjustments,
            energyAmount: $sameAmount ? $this->decimal($row->energy_amount) : null,
            f1: $sameQuantity ? $this->decimal($row->quantity_f1) : null,
            f2: $sameQuantity ? $this->decimal($row->quantity_f2) : null,
            f3: $sameQuantity ? $this->decimal($row->quantity_f3) : null,
        );
    }

    /**
     * The unit of the utility.
     *
     * @throws NotFoundHttpException if the utility has no consumption
     */
    private function unit(): ConsumptionUnit
    {
        return ConsumptionUnit::forUtility($this->utility()->type) ?? throw new NotFoundHttpException;
    }

    /**
     * Fails a period longer than a bill can be.
     *
     * @param  string  $attribute  the field, `period_end`
     * @param  mixed  $value  its value
     * @param  Closure(string): mixed  $fail  called with the message
     */
    private function periodFits(string $attribute, mixed $value, Closure $fail): void
    {
        $start = $this->input('period_start');
        $first = is_string($start) ? CarbonImmutable::createFromFormat('!Y-m-d', $start) : false;
        $last = is_string($value) ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : false;

        if ($first instanceof CarbonImmutable && $last instanceof CarbonImmutable && $first->lessThanOrEqualTo($last) && ConsumptionCheck::tooLong($first, $last)) {
            $fail(__('The period lasts at most :days days.', ['days' => ConsumptionCheck::MAX_PERIOD_DAYS]));
        }
    }

    /**
     * Fails a consumption of zero: there is nothing to save.
     *
     * @param  string  $attribute  the field, `quantity`
     * @param  mixed  $value  its value
     * @param  Closure(string): mixed  $fail  called with the message
     */
    private function notZero(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_numeric($value) && (float) $value === 0.0) {
            $fail(__('Write a consumption other than zero.'));
        }
    }

    /**
     * Fails a document that is not a bill of the utility.
     *
     * @param  string  $attribute  the field, `document_id`
     * @param  mixed  $value  its value
     * @param  Closure(string): mixed  $fail  called with the message
     */
    private function billOfUtility(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || ! $this->utility()->documents()->whereKey((int) $value)->exists()) {
            $fail(__('Choose a bill of this utility.'));
        }
    }

    /**
     * A decimal column as a number.
     *
     * @param  numeric-string|int|float|null  $value  the value of the column
     */
    private function decimal(string|int|float|null $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
