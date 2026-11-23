<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Exceptions\Banks\BankFeedFailed;
use App\Models\BankConnection;
use App\Services\Banks\BankInstitutions;
use App\Support\Banks\BankInstitution;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The choice of a bank to connect or connect again: only an administrator, only a bank of the list.
 */
final class StartBankConnectionRequest extends FormRequest
{
    /**
     * An administrator, while the link to the bank is on for the family.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', BankConnection::class);
    }

    /**
     * @return array<string, list<Closure|string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'institution' => ['required', 'string', function (string $attribute, mixed $value, callable $fail): void {
                try {
                    $banks = resolve(BankInstitutions::class)->all();
                } catch (BankFeedFailed $failure) {
                    // Not the person's mistake: the provider does not answer
                    report($failure);
                    $fail(__('We cannot load the list of banks right now. Try again in a moment.'));

                    return;
                }

                if (! is_string($value) || collect($banks)->doesntContain(static fn (BankInstitution $bank): bool => $bank->name === $value)) {
                    $fail(__('Choose a bank from the list.'));
                }
            }],
        ];
    }

    /**
     * The bank chosen.
     */
    public function institution(): BankInstitution
    {
        /** @var BankInstitution */
        return resolve(BankInstitutions::class)->find($this->string('institution')->toString());
    }
}
