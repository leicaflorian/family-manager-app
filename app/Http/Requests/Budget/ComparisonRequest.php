<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Http\Requests\Concerns\ReadsMonth;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The address of *Planned and spent*: `?month=2026-10`. Whatever is not a month is a wrong address and answers 404.
 */
final class ComparisonRequest extends FormRequest
{
    use ReadsMonth;

    /**
     * The rules of the address.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::MONTH_RULES;
    }
}
