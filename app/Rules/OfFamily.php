<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\CurrentFamily;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Come la regola `exists`, ma solo tra i dati della famiglia corrente: un id
 * di un'altra famiglia (un ente dei Rossi in un documento dei Bianchi) non
 * passa, anche se esiste.
 */
final readonly class OfFamily implements ValidationRule
{
    /**
     * @param  string  $table  la tabella in cui cercare l'id, per esempio `organizations`
     */
    public function __construct(private string $table) {}

    /**
     * Controlla che l'id esista nella tabella e sia della famiglia corrente.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $famiglia = CurrentFamily::id();

        $esiste = $famiglia !== null
            && (is_int($value) || (is_string($value) && ctype_digit($value)))
            && DB::table($this->table)->where('id', $value)->where('family_id', $famiglia)->exists();

        if (! $esiste) {
            $fail('validation.exists')->translate();
        }
    }
}
