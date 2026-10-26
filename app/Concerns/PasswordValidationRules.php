<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return [...$this->passwordRulesWithoutConfirmation(), 'confirmed'];
    }

    /**
     * Le regole di una password nuova scritta una volta sola, come nel modulo *Iscriviti*.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRulesWithoutConfirmation(): array
    {
        return ['required', 'string', Password::default()];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
