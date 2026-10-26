<?php

declare(strict_types=1);

namespace App\Http\Requests\Famiglia;

use App\Enums\Ruolo;
use App\Enums\RuoloPersona;
use App\Models\Invitation;
use App\Models\User;
use App\Rules\OfFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Il modulo *Invita un familiare*: l'email, chi è (una persona che c'è già o una
 * nuova) e cosa può fare.
 */
final class MandaInvitoRequest extends FormRequest
{
    /**
     * Solo un `admin` invita.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
    }

    /**
     * Le regole dei campi. Senza `person_id` servono nome e cognome della persona nuova.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::enum(Ruolo::class)],
            // Una persona della famiglia che non ha ancora un account.
            'person_id' => ['nullable', 'integer', new OfFamily('people'), Rule::exists('people', 'id')->whereNull('user_id')],
            'first_name' => ['required_without:person_id', 'nullable', 'string', 'max:255'],
            'last_name' => ['required_without:person_id', 'nullable', 'string', 'max:255'],
            'person_role' => ['nullable', Rule::enum(RuoloPersona::class)],
        ];
    }

    /**
     * I nomi dei campi nei messaggi.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email',
            'role' => 'cosa può fare',
            'person_id' => 'chi è',
            'first_name' => 'nome',
            'last_name' => 'cognome',
            'person_role' => 'nella famiglia è',
        ];
    }
}
