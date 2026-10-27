<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The *Invitation accepted* form: the chosen password, typed once.
 */
final class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['password' => $this->passwordRulesWithoutConfirmation()];
    }
}
