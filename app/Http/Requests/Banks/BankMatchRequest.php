<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Models\PlannedExpense;
use App\Services\Banks\BankFeedAvailability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * What the three calls of the field *How it shows in the bank* share: who may make them (who can create a planned
 * expense, while the link to the bank is on) and the direction and account the transactions are looked at in.
 */
class BankMatchRequest extends FormRequest
{
    /**
     * Only who can create a planned expense; 404 while the link to the bank is off.
     *
     * @throws NotFoundHttpException while the link to the bank is off for the family
     */
    public function authorize(): bool
    {
        abort_unless(BankFeedAvailability::on(), 404);

        return (bool) $this->user()?->can('create', PlannedExpense::class);
    }

    /**
     * The rules of the direction and of the account.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:expense,income'],
            'account_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The direction of the form: `expense` looks at outgoing transactions, `income` at incoming ones.
     */
    public function kind(): string
    {
        return $this->string('kind')->toString();
    }

    /**
     * The account the form has chosen; null looks at every linked account of the family.
     */
    public function accountId(): ?int
    {
        return $this->filled('account_id') ? $this->integer('account_id') : null;
    }
}
