<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Inviti\AccettaInvito;
use App\Data\InvitoData;
use App\Http\Requests\Famiglia\AccettaInvitoRequest;
use App\Models\Invitation;
use App\Support\CurrentFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Il link dell'invito, aperto da chi lo riceve: non serve essere entrati.
 */
final class InvitoController extends Controller
{
    /**
     * *Invito accettato*, o *Questo invito non vale più* se è scaduto, già usato o inventato.
     *
     * @param  string  $token  il codice del link
     */
    public function show(string $token): Response
    {
        $invito = Invitation::valido($token);

        if (! $invito instanceof Invitation) {
            return Inertia::render('auth/InvitoNonValido');
        }

        return Inertia::render('auth/InvitoAccettato', [
            'token' => $token,
            'invito' => CurrentFamily::per($invito->family_id, static fn (): InvitoData => InvitoData::daInvito($invito)),
        ]);
    }

    /**
     * Crea l'account, entra e porta alla Home. Un invito che non vale dà 404.
     *
     * @param  string  $token  il codice del link
     */
    public function store(AccettaInvitoRequest $request, string $token, AccettaInvito $accetta): RedirectResponse
    {
        $invito = Invitation::valido($token);
        abort_unless($invito instanceof Invitation, 404);

        Auth::login($accetta->handle($invito, $request->string('password')->toString()));
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
