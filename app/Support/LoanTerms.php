<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\LoanStatus;
use App\Models\Loan;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * What the rules of a loan read from it: the status, the two dates and the amounts, already as dates and numbers.
 * An empty or non positive amount is null, like `positive()` of the Directus rules.
 */
final readonly class LoanTerms
{
    /**
     * @param  LoanStatus  $status  Active or paid off.
     * @param  ?CarbonImmutable  $start  The date of the first instalment.
     * @param  ?CarbonImmutable  $end  The date of the last instalment, when written.
     * @param  ?int  $count  The number of instalments.
     * @param  ?float  $installment  The amount of one instalment.
     * @param  ?float  $finalInstallment  The amount of the last instalment (balloon payment), when it differs.
     * @param  ?float  $financed  The amount financed.
     */
    public function __construct(
        public LoanStatus $status,
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $end,
        public ?int $count,
        public ?float $installment,
        public ?float $finalInstallment,
        public ?float $financed,
    ) {}

    /**
     * The terms of a saved loan.
     *
     * @param  Loan  $loan  The loan.
     */
    public static function of(Loan $loan): self
    {
        return self::fromForm([
            'status' => $loan->status,
            'start_date' => $loan->start_date,
            'end_date' => $loan->end_date,
            'installments_count' => $loan->installments_count,
            'installment_amount' => $loan->installment_amount,
            'final_installment_amount' => $loan->final_installment_amount,
            'total_amount' => $loan->total_amount,
        ]);
    }

    /**
     * The terms from the values of a form, where numbers and dates may arrive as text.
     *
     * @param  array<string, mixed>  $values  The columns of `loans` that matter: status, start_date, end_date, installments_count, installment_amount, final_installment_amount, total_amount.
     */
    public static function fromForm(array $values): self
    {
        $status = $values['status'] ?? null;
        $count = self::positive($values['installments_count'] ?? null);
        $count = $count === null ? null : (int) floor($count);

        return new self(
            $status instanceof LoanStatus ? $status : (is_string($status) ? LoanStatus::tryFrom($status) : null) ?? LoanStatus::Active,
            self::day($values['start_date'] ?? null),
            self::day($values['end_date'] ?? null),
            $count === 0 ? null : $count,
            self::positive($values['installment_amount'] ?? null),
            self::positive($values['final_installment_amount'] ?? null),
            self::positive($values['total_amount'] ?? null),
        );
    }

    /**
     * The same terms with another number of instalments, for the one worked out from the dates.
     *
     * @param  ?int  $count  The number of instalments.
     */
    public function withCount(?int $count): self
    {
        return new self($this->status, $this->start, $this->end, $count, $this->installment, $this->finalInstallment, $this->financed);
    }

    /**
     * The same terms with another last date, for the one worked out from the start and the count.
     *
     * @param  ?CarbonImmutable  $end  The date of the last instalment.
     */
    public function withEnd(?CarbonImmutable $end): self
    {
        return new self($this->status, $this->start, $end, $this->count, $this->installment, $this->finalInstallment, $this->financed);
    }

    /**
     * A day from a date or from text such as `2026-01-05` or `2026-01-05T00:00:00.000Z`; null when it is not one.
     *
     * @param  mixed  $value  The date, text or nothing.
     */
    private static function day(mixed $value): ?CarbonImmutable
    {
        $text = match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_string($value) => substr($value, 0, 10),
            default => null,
        };

        if ($text === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $parts) !== 1 || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return CarbonImmutable::parse($text)->startOfDay();
    }

    /**
     * A number above zero from a number or text; null when empty, not a number, or zero or less.
     *
     * @param  mixed  $value  The number, text or nothing.
     */
    private static function positive(mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value) && (! is_string($value) || ! is_numeric($value))) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) && $number > 0 ? $number : null;
    }
}
