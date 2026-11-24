<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * *Settings → What you use*: the two switches. Only an `admin` of the family changes them.
 */
final class UpdateFeaturesRequest extends FormRequest
{
    /**
     * Only whoever manages the features of the family (`FamilyPolicy::manageFeatures`).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && Gate::forUser($user)->allows('manageFeatures', $user->family);
    }

    /**
     * Both switches, always sent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'budget' => ['required', 'boolean'],
            'mail' => ['required', 'boolean'],
        ];
    }
}
