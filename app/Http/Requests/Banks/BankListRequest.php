<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Enums\BankRowStatus;
use App\Models\BankTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The filters of the list *All* of *From the bank*: words, account, status, month and direction. Every one is optional.
 */
final class BankListRequest extends FormRequest
{
    /**
     * Whoever may see the bank transactions.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', BankTransaction::class);
    }

    /**
     * `?show=ignored` is `?show=all` with the status *ignored*, so the old address keeps answering.
     */
    protected function prepareForValidation(): void
    {
        if ($this->query('show') === 'ignored' && ! $this->filled('status')) {
            $this->merge(['status' => BankRowStatus::Ignored->value]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'account' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::enum(BankRowStatus::class)],
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'direction' => ['nullable', Rule::in(['expense', 'income'])],
        ];
    }

    /**
     * The words to look for, split on spaces.
     *
     * @return list<string>
     */
    public function words(): array
    {
        return array_values(array_filter(preg_split('/\s+/u', trim($this->string('q')->toString())) ?: [], static fn (string $word): bool => $word !== ''));
    }

    /**
     * The account chosen, if any.
     */
    public function accountId(): ?int
    {
        return $this->filled('account') ? $this->integer('account') : null;
    }

    /**
     * The status chosen, if any.
     */
    public function status(): ?BankRowStatus
    {
        return $this->filled('status') ? BankRowStatus::tryFrom($this->string('status')->toString()) : null;
    }

    /**
     * The month chosen (`yyyy-mm`), if any.
     */
    public function month(): ?string
    {
        return $this->filled('month') ? $this->string('month')->toString() : null;
    }

    /**
     * The direction chosen (`expense` or `income`), if any.
     */
    public function direction(): ?string
    {
        return $this->filled('direction') ? $this->string('direction')->toString() : null;
    }

    /**
     * The values chosen, as the page shows them in its filters.
     *
     * @return array{q: ?string, account: ?int, status: ?string, month: ?string, direction: ?string}
     */
    public function chosen(): array
    {
        return [
            'q' => $this->words() === [] ? null : implode(' ', $this->words()),
            'account' => $this->accountId(),
            'status' => $this->status()?->value,
            'month' => $this->month(),
            'direction' => $this->direction(),
        ];
    }
}
