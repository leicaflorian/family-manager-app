<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Invitation;
use App\Models\Person;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un invito come lo vede chi lo riceve, nella pagina *Invito accettato*.
 */
#[TypeScript]
final class InvitoData extends Data
{
    /**
     * @param  string  $nome  il nome di chi è invitato, per il saluto
     * @param  string  $famiglia  il nome della famiglia
     * @param  string  $chiInvita  il nome di chi ha mandato l'invito
     * @param  string  $email  l'email dell'account che nasce
     * @param  string  $scade  fino a quando vale, `gg/mm/aaaa`
     * @param  list<PersonaBreveData>  $persone  le altre persone della famiglia, per le facce
     */
    public function __construct(
        public readonly string $nome,
        public readonly string $famiglia,
        public readonly string $chiInvita,
        public readonly string $email,
        public readonly string $scade,
        public readonly array $persone,
    ) {}

    /**
     * I dati di un invito, letti nella sua famiglia.
     *
     * @param  Invitation  $invito  l'invito, con la famiglia impostata come corrente
     */
    public static function daInvito(Invitation $invito): self
    {
        return new self(
            nome: $invito->person->first_name ?? (string) $invito->first_name,
            famiglia: $invito->family->name,
            chiInvita: Str::before((string) $invito->creator?->name, ' '),
            email: $invito->email,
            scade: $invito->expires_at->format('d/m/Y'),
            persone: array_values(Person::query()->whereKeyNot($invito->person_id)->orderBy('id')->get()
                ->map(PersonaBreveData::daPersona(...))->all()),
        );
    }
}
