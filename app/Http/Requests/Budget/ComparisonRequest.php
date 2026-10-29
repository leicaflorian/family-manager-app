<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The address of *Planned and spent*: `?month=2026-10`. Whatever is not a month is a wrong address and answers 404.
 */
final class ComparisonRequest extends FormRequest
{
    /**
     * The rules of the address.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['month' => ['nullable', 'date_format:Y-m']];
    }

    /**
     * The month asked, else the current one.
     */
    public function month(): CarbonImmutable
    {
        $month = $this->query('month');

        return is_string($month) && $month !== ''
            ? CarbonImmutable::createFromFormat('!Y-m', $month, 'Europe/Rome') ?: throw new NotFoundHttpException
            : CarbonImmutable::today('Europe/Rome')->startOfMonth();
    }

    /**
     * A wrong address is a page that does not exist, not an error to correct.
     *
     * @param  Validator  $validator  The validator that failed.
     *
     * @throws NotFoundHttpException always
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new NotFoundHttpException;
    }
}
