<?php

declare(strict_types=1);

namespace App\Http\Requests\Banks;

use App\Models\BankConnection;
use App\Services\Banks\BankFeedAvailability;
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
        abort_unless(BankFeedAvailability::on(), 404);

        return Gate::allows('create', BankConnection::class);
    }

    /**
     * @return array<string, list<Closure|string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'institution' => ['required', 'string', function (string $attribute, mixed $value, callable $fail): void {
                if (! is_string($value) || ! resolve(BankInstitutions::class)->find($value) instanceof BankInstitution) {
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
