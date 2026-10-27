<?php

declare(strict_types=1);

namespace App\Http\Requests\Impostazioni;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * *Il mio account*: nome, cognome ed email.
 */
final class AggiornaAccountRequest extends FormRequest
{
    /**
     * Le regole dei campi: l'email non può essere quella di un altro account.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cognome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()?->id)],
        ];
    }
}
