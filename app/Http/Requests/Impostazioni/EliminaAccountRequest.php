<?php

declare(strict_types=1);

namespace App\Http\Requests\Impostazioni;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *Elimina il mio account*: si conferma con la password.
 */
final class EliminaAccountRequest extends FormRequest
{
    /**
     * Le regole dei campi.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'current_password']];
    }
}
