<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Inviti\MandaInvito;
use App\Data\InvitoMandatoData;
use App\Data\OpzioneData;
use App\Data\PersonaBreveData;
use App\Enums\Ruolo;
use App\Enums\RuoloPersona;
use App\Http\Requests\Famiglia\MandaInvitoRequest;
use App\Models\Invitation;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Invita un familiare*: il modulo, l'invio e la pagina con il link da copiare.
 * Solo un `admin` invita.
 */
final class InvitiController extends Controller
{
    /**
     * Il modulo: chi è si sceglie tra le persone senza account, o è una persona nuova.
     */
    public function create(): Response
    {
        Gate::authorize('create', Invitation::class);

        return Inertia::render('famiglia/Invita', [
            'persone' => Person::query()->whereNull('user_id')->orderBy('first_name')->get()
                ->map(PersonaBreveData::daPersona(...))->values()->all(),
            'livelli' => OpzioneData::elenco(Ruolo::opzioni()),
            'ruoli' => OpzioneData::elenco(RuoloPersona::opzioni()),
        ]);
    }

    /**
     * Crea l'invito, manda l'email e porta alla pagina con il link.
     */
    public function store(MandaInvitoRequest $request, MandaInvito $manda): RedirectResponse
    {
        /** @var array{email: string, role: string, person_id?: int|null, first_name?: string|null, last_name?: string|null, person_role?: string|null} $dati */
        $dati = $request->validated();

        return to_route('famiglia.inviti.show', $manda->handle($dati));
    }

    /**
     * L'invito appena mandato, con il link da copiare se l'email non arriva.
     */
    public function show(Invitation $invitation): Response
    {
        Gate::authorize('create', Invitation::class);

        return Inertia::render('famiglia/InvitoMandato', [
            'invito' => InvitoMandatoData::daInvito($invitation),
        ]);
    }
}
