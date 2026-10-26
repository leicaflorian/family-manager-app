<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Invitation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un invito appena mandato, con il link da copiare.
 */
#[TypeScript]
final class InvitoMandatoData extends Data
{
    /**
     * @param  string  $email  a chi è andato
     * @param  string  $link  il link per entrare
     * @param  string  $scade  fino a quando vale, `gg/mm/aaaa`
     */
    public function __construct(
        public readonly string $email,
        public readonly string $link,
        public readonly string $scade,
    ) {}

    /**
     * I dati di un invito mandato.
     *
     * @param  Invitation  $invito  l'invito
     */
    public static function daInvito(Invitation $invito): self
    {
        return new self($invito->email, route('invito.show', $invito->token), $invito->expires_at->format('d/m/Y'));
    }
}
