<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Http\Requests\Concerns\ReadsBankFilter;
use App\Models\BankFilter;
use App\Models\BankTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The preview of a bank filter while it is written (`POST budget/bank/filters/preview`): the same fields as its form,
 * none required, `filter`, the id of the filter being changed, and `drop`, the movement *It is not of this group*
 * takes out. Everyone of the family may ask it; the ids named
 * are looked for in the family first (404 for one of another family).
 */
final class BankFilterPreviewRequest extends FormRequest
{
    use ReadsBankFilter;

    /** The filter being changed, read once. */
    private ?BankFilter $filter = null;

    /** The movement taken out, read once. */
    private ?BankTransaction $drop = null;

    /**
     * Everyone of the family.
     *
     * @throws ModelNotFoundException if an id named is not of the family
     */
    public function authorize(): bool
    {
        $this->findSentIds();
        $id = $this->input('filter');

        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $this->filter = BankFilter::query()->findOrFail((int) $id);
        }

        $drop = $this->input('drop');

        if (is_int($drop) || (is_string($drop) && ctype_digit($drop))) {
            $this->drop = BankTransaction::query()->findOrFail((int) $drop);
        }

        return (bool) $this->user()?->can('viewAny', BankFilter::class);
    }

    /**
     * The rules: the shapes only; what cannot be used takes nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'integer'],
            'drop' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:100'],
            'contains' => ['nullable', 'array', 'max:20'],
            'contains.*' => ['nullable', 'string', 'max:100'],
            'excludes' => ['nullable', 'array', 'max:20'],
            'excludes.*' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', 'string'],
            'financial_account_id' => ['nullable', 'integer'],
            'amount_min' => ['nullable', 'numeric'],
            'amount_max' => ['nullable', 'numeric'],
            'pattern' => ['nullable', 'string', 'max:200'],
            'fee_mode' => ['nullable', 'string'],
            'fee_contains' => ['nullable', 'array', 'max:20'],
            'fee_contains.*' => ['nullable', 'string', 'max:100'],
            'fee_excludes' => ['nullable', 'array', 'max:20'],
            'fee_excludes.*' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string'],
            'planned_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'title_with_month' => ['nullable', 'boolean'],
            'transfer_account_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * The id of the filter being changed, which never conflicts with itself; null for a new one.
     */
    public function changing(): ?int
    {
        return $this->filter?->id;
    }

    /**
     * The movement *It is not of this group* takes out, null when none.
     */
    public function dropped(): ?BankTransaction
    {
        return $this->drop;
    }
}
