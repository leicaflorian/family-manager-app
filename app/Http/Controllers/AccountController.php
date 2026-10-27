<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Account\AggiornaAccount;
use App\Actions\Account\CambiaFoto;
use App\Data\AccountData;
use App\Http\Requests\Impostazioni\AggiornaAccountRequest;
use App\Http\Requests\Impostazioni\CambiaPasswordRequest;
use App\Http\Requests\Impostazioni\CaricaFotoRequest;
use App\Http\Requests\Impostazioni\EliminaAccountRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Il mio account*: i dati, la password, la foto, l'eliminazione. Vale solo per chi
 * è entrato, non per la famiglia.
 */
final class AccountController extends Controller
{
    /**
     * La pagina.
     */
    public function edit(#[CurrentUser] User $utente): Response
    {
        return Inertia::render('impostazioni/Account', ['account' => AccountData::daUtente($utente)]);
    }

    /**
     * Salva nome, cognome ed email.
     */
    public function update(AggiornaAccountRequest $request, #[CurrentUser] User $utente, AggiornaAccount $aggiorna): RedirectResponse
    {
        $aggiorna->handle(
            $utente,
            $request->string('nome')->toString(),
            $request->string('cognome')->toString(),
            $request->string('email')->toString(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Salvato.']);

        return to_route('account.edit');
    }

    /**
     * Cambia la password.
     */
    public function password(CambiaPasswordRequest $request, #[CurrentUser] User $utente): RedirectResponse
    {
        $utente->update(['password' => $request->string('password')->toString()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password cambiata.']);

        return to_route('account.edit');
    }

    /**
     * Mette la foto nuova.
     */
    public function foto(CaricaFotoRequest $request, #[CurrentUser] User $utente, CambiaFoto $cambia): RedirectResponse
    {
        $foto = $request->file('foto');

        if ($foto instanceof \Illuminate\Http\UploadedFile) {
            $cambia->handle($utente, $foto);
        }

        return to_route('account.edit');
    }

    /**
     * Elimina l'account e porta ad Accedi. La persona resta nella famiglia,
     * senza account. L'ultimo `admin` non si elimina.
     *
     * @throws ValidationException se è l'ultimo admin della famiglia
     */
    public function destroy(EliminaAccountRequest $request, #[CurrentUser] User $utente): RedirectResponse
    {
        if ($utente->ultimoAdmin()) {
            throw ValidationException::withMessages(['password' => 'Sei l\'unico amministratore della famiglia: prima dai il livello «Tutto, come te» a un\'altra persona.']);
        }

        Auth::logout();
        $utente->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
