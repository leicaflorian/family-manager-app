<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Http\Requests\Concerns\ReadsMonth;
use App\Models\Expense;
use App\Models\Income;
use App\Rules\OfFamily;
use App\Services\Movements;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The address of *Movements*: `?month=2026-10&kind=expense&account=3&movement=expense:12`. Whatever is not a month,
 * a kind, an account or a movement of the family is a wrong address: it answers 404, as a wrong id does.
 */
final class MovementIndexRequest extends FormRequest
{
    use ReadsMonth;

    /**
     * The rules of the address.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...self::MONTH_RULES,
            'kind' => ['nullable', 'in:expense,income'],
            'account' => ['nullable', new OfFamily('financial_accounts')],
            'movement' => ['nullable', 'regex:/^(expense|income):[0-9]+$/'],
        ];
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
}
