<?php

declare(strict_types=1);

namespace App\Http\Requests\Famiglia;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Il modulo *Invito accettato*: la password scelta, una volta sola.
 */
final class AccettaInvitoRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Le regole dei campi.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['password' => $this->passwordRulesWithoutConfirmation()];
    }
}
