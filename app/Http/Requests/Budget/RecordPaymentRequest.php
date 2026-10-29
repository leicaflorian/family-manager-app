<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Http\Requests\Concerns\UploadsFiles;
use App\Models\Expense;
use App\Models\Income;
use App\Rules\OfFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The window *I paid it* / *It arrived*: when, how much, with which account and an optional receipt.
 */
final class RecordPaymentRequest extends FormRequest
{
    use UploadsFiles;

    /**
     * Whoever may create an expense or an income records payments.
     */
    public function authorize(): bool
    {
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
            'amount' => ['required', 'numeric', 'min:0'],
            'financial_account_id' => ['nullable', new OfFamily('financial_accounts')],
            'receipt' => ['nullable', ...array_slice(self::FILE, 1)],
        ];
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
