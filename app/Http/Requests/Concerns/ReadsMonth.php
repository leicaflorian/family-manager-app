<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * For the requests of a budget page with `?month=2026-10`: the month of the address, the current one when none is
 * asked, and a wrong address answering 404. The request adds `MONTH_RULES` to its own rules.
 */
trait ReadsMonth
{
    /** The rule of the `month` of the address. */
    public const array MONTH_RULES = ['month' => ['nullable', 'date_format:Y-m']];

    /**
     * The month asked, else the current one.
     *
     * @throws NotFoundHttpException if the month cannot be read
     */
    public function month(): CarbonImmutable
    {
        $month = $this->query('month');

        return is_string($month) && $month !== ''
            ? CarbonImmutable::createFromFormat('!Y-m', $month, 'Europe/Rome') ?: throw new NotFoundHttpException
            : CarbonImmutable::today('Europe/Rome')->startOfMonth();
    }

    /**
     * A wrong address is not an error to correct in a form: it is a page that does not exist.
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
