<?php

declare(strict_types=1);

namespace App\Http\Requests\Famiglia;

use App\Enums\RuoloPersona;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * I due passi del *Benvenuto*: chi siete (le persone da aggiungere) e la casa,
 * che si può lasciare per dopo.
 */
final class BenvenutoRequest extends FormRequest
{
    /**
     * Le regole dei campi.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'persone' => ['present', 'array', 'max:20'],
            'persone.*.first_name' => ['required', 'string', 'max:255'],
            'persone.*.last_name' => ['required', 'string', 'max:255'],
            'persone.*.role' => ['required', Rule::enum(RuoloPersona::class)],
            'casa' => ['nullable', 'array'],
            'casa.name' => ['required_with:casa', 'string', 'max:255'],
            'casa.address' => ['nullable', 'string', 'max:255'],
            'casa.city' => ['nullable', 'string', 'max:255'],
            'casa.is_owned' => ['boolean'],
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
            'persone.*.first_name' => 'nome',
            'persone.*.last_name' => 'cognome',
            'persone.*.role' => 'nella famiglia è',
            'casa.name' => 'come la chiamate',
            'casa.address' => 'indirizzo',
            'casa.city' => 'città',
        ];
    }
}
