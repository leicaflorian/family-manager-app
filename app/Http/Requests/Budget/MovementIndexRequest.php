<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Models\Expense;
use App\Models\Income;
use App\Rules\OfFamily;
use App\Services\Movements;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The address of *Movements*: `?month=2026-10&kind=expense&account=3&movement=expense:12`. Whatever is not a month,
 * a kind, an account or a movement of the family is a wrong address: it answers 404, as a wrong id does.
 */
final class MovementIndexRequest extends FormRequest
{
    /**
     * The rules of the address.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'kind' => ['nullable', 'in:expense,income'],
            'account' => ['nullable', new OfFamily('financial_accounts')],
            'movement' => ['nullable', 'regex:/^(expense|income):[0-9]+$/'],
        ];
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
     * The kind to show, null for both.
     */
    public function kind(): ?string
    {
        return $this->filled('kind') ? $this->string('kind')->toString() : null;
    }

    /**
     * The account to show, null for all.
     */
    public function account(): ?int
    {
        return $this->filled('account') ? $this->integer('account') : null;
    }

    /**
     * The movement to open beside the list, if one is asked.
     *
     * @throws ModelNotFoundException if the family has no such movement (Laravel answers 404)
     */
    public function movement(): Expense|Income|null
    {
        if (! $this->filled('movement')) {
            return null;
        }

        [$kind, $id] = explode(':', $this->string('movement')->toString());

        return Movements::find($kind, $id);
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
