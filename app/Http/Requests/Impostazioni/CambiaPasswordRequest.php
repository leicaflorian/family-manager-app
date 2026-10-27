<?php

declare(strict_types=1);

namespace App\Http\Requests\Impostazioni;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Il pannello *Cambia la password*: quella di adesso e quella nuova.
 */
final class CambiaPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Le regole dei campi.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password_attuale' => ['required', 'string', 'current_password'],
            'password' => $this->passwordRulesWithoutConfirmation(),
        ];
    }

    /**
     * I nomi dei campi nei messaggi.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password_attuale' => 'password di adesso', 'password' => 'password nuova'];
    }
}
