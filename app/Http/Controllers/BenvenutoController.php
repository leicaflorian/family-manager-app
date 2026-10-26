<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Famiglie\ConcludiBenvenuto;
use App\Data\OpzioneData;
use App\Data\PersonaBreveData;
use App\Enums\RuoloPersona;
use App\Http\Requests\Famiglia\BenvenutoRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Il *Benvenuto* dopo l'iscrizione: chi siete e la casa. Finito una volta, non torna.
 */
final class BenvenutoController extends Controller
{
    /**
     * I due passi, con le persone che ci sono già (chi si è iscritto).
     */
    public function show(#[CurrentUser] User $utente): Response|RedirectResponse
    {
        if ($utente->family->accolta()) {
            return to_route('dashboard');
        }

        return Inertia::render('benvenuto/Benvenuto', [
            'persone' => Person::query()->orderBy('id')->get()->map(PersonaBreveData::daPersona(...))->values()->all(),
            'ruoli' => OpzioneData::elenco(RuoloPersona::opzioni()),
        ]);
    }

    /**
     * *Fatto, entriamo*: salva persone e casa e porta alla Home.
     */
    public function store(BenvenutoRequest $request, #[CurrentUser] User $utente, ConcludiBenvenuto $concludi): RedirectResponse
    {
        if (! $utente->family->accolta()) {
            /** @var list<array{first_name: string, last_name: string, role: string}> $persone */
            $persone = $request->validated('persone');
            /** @var array{name: string, address?: string|null, city?: string|null, is_owned?: bool}|null $casa */
            $casa = $request->validated('casa');
            $concludi->handle($utente->family, $persone, $casa);
        }

        return to_route('dashboard');
    }
}
