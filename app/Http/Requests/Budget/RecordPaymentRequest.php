<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Http\Requests\Concerns\ResolvesDeadline;
use App\Http\Requests\Concerns\UploadsFiles;
use App\Models\Expense;
use App\Models\Income;
use App\Models\InsurancePolicy;
use App\Rules\OfFamily;
use App\Support\PolicyRenewal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The window *I paid it* / *It arrived*: when, how much, with which account and an optional receipt; `from_agenda` sends the answer back to the Agenda.
 * For the premium of a policy near its expiry, `renewal` says what happens to the policy: `only` the payment, `same`
 * company renewed until `new_expiry`, or `changed` company. `policy_expiry` is the expiry the page showed.
 */
final class RecordPaymentRequest extends FormRequest
{
    use ResolvesDeadline;
    use UploadsFiles;

    /**
     * Whoever may create an expense or an income records payments. The address is resolved first:
     * a wrong one answers 404 before anyone is refused or the body is read.
     */
    public function authorize(): bool
    {
        $this->deadline();
        $user = $this->user();

        return $user !== null && $user->can('create', Expense::class) && $user->can('create', Income::class);
    }

    /**
     * The rules of the payment.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'financial_account_id' => ['nullable', new OfFamily('financial_accounts')],
            'receipt' => ['nullable', ...array_slice(self::FILE, 1)],
            'from_agenda' => ['sometimes', 'boolean'],
            'renewal' => ['sometimes', Rule::in(PolicyRenewal::CHOICES)],
            'new_expiry' => ['required_if:renewal,same', 'nullable', 'date_format:Y-m-d'],
            'policy_expiry' => ['required_if:renewal,same,changed', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * A renewal is refused when the due date is not the premium of a policy, or the person may not renew it. A
     * policy that is no longer offered (renewed or cancelled elsewhere since the page opened) is not refused: the
     * payment is recorded alone, see `renewalPolicy()`.
     *
     * @return list<callable(Validator):void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->renewal() !== 'only' && ! PolicyRenewal::linkedPolicy($this->deadline()) instanceof InsurancePolicy) {
                    $validator->errors()->add('renewal', __('This policy cannot be renewed here.'));
                }
            },
        ];
    }

    /**
     * What happens to the policy: `only` the payment (also when nothing is sent), `same` or `changed`.
     */
    public function renewal(): string
    {
        $renewal = $this->input('renewal', 'only');

        return is_string($renewal) && in_array($renewal, PolicyRenewal::CHOICES, true) ? $renewal : 'only';
    }

    /**
     * The policy the choice acts on: the one the due date can renew, while its expiry is still the one the page
     * showed. Null when the page is stale (the policy was renewed, replaced or cancelled since): then the choice
     * falls back to only the payment.
     */
    public function renewalPolicy(): ?InsurancePolicy
    {
        $policy = PolicyRenewal::policyFor($this->deadline(), CarbonImmutable::today('Europe/Rome'));

        return $policy?->expiry_date?->toDateString() === $this->day('policy_expiry')?->toDateString() ? $policy : null;
    }

    /**
     * A date of the form, `Y-m-d`, as a day in Rome.
     *
     * @param  string  $field  `new_expiry` or `policy_expiry`
     */
    public function day(string $field): ?CarbonImmutable
    {
        $value = $this->string($field)->toString();

        return $value === '' ? null : (CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Europe/Rome') ?: null);
    }

    /**
     * The messages of the receipt that say what to do.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt.mimes' => __('Only PDF, JPG, PNG and HEIC files can be uploaded.'),
            'receipt.max' => __('Each file can be at most 25 MB.'),
            'receipt.uploaded' => __('Each file can be at most 25 MB.'),
        ];
    }
}
