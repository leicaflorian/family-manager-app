<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\AccountData;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'indice delle *Impostazioni*: il mio account e le impostazioni della famiglia.
 */
final class ImpostazioniController extends Controller
{
    /**
     * Le voci che ci sono in questa tappa: il mio account e le categorie.
     */
    public function __invoke(#[CurrentUser] User $utente): Response
    {
        return Inertia::render('impostazioni/Impostazioni', [
            'account' => AccountData::daUtente($utente),
            'categoriePrincipali' => DocumentCategory::query()->whereNull('parent_id')->count(),
        ]);
    }
}
