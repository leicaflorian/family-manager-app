<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BenvenutoController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ImpostazioniController;
use App\Http\Controllers\InvitiController;
use App\Http\Controllers\InvitoController;
use App\Http\Middleware\PortaAlBenvenuto;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// Il link dell'invito: lo apre chi non ha ancora un account.
Route::middleware('guest')->group(function (): void {
    Route::get('invito/{token}', [InvitoController::class, 'show'])->name('invito.show');
    Route::post('invito/{token}', [InvitoController::class, 'store'])->name('invito.store');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('benvenuto', [BenvenutoController::class, 'show'])->name('benvenuto');
    Route::post('benvenuto', [BenvenutoController::class, 'store'])->name('benvenuto.store');

    Route::middleware(PortaAlBenvenuto::class)->group(function (): void {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');
        Route::inertia('aree', 'Aree')->name('aree');

        Route::get('famiglia/invita', [InvitiController::class, 'create'])->name('famiglia.invita');
        Route::post('famiglia/inviti', [InvitiController::class, 'store'])->name('famiglia.inviti.store');
        Route::get('famiglia/inviti/{invitation}', [InvitiController::class, 'show'])->name('famiglia.inviti.show');

        Route::get('impostazioni', ImpostazioniController::class)->name('impostazioni');
        Route::get('impostazioni/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::patch('impostazioni/account', [AccountController::class, 'update'])->name('account.update');
        Route::put('impostazioni/account/password', [AccountController::class, 'password'])->middleware('throttle:6,1')->name('account.password');
        Route::post('impostazioni/account/foto', [AccountController::class, 'foto'])->name('account.foto');
        Route::delete('impostazioni/account', [AccountController::class, 'destroy'])->name('account.destroy');
        Route::resource('impostazioni/categorie', CategorieController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['categorie' => 'categoria'])
            ->names('categorie');

        Route::get('file/{file}', [FileController::class, 'show'])->name('file.show');
        Route::get('file/{file}/scarica', [FileController::class, 'scarica'])->name('file.scarica');

        // Le pagine che arrivano con i prossimi task: le voci del menu portano già qui.
        Route::inertia('documenti', 'InArrivo', ['titolo' => 'Documenti'])->name('documenti.index');
        Route::inertia('famiglia', 'InArrivo', ['titolo' => 'Famiglia'])->name('famiglia');
        Route::inertia('carica', 'InArrivo', ['titolo' => 'Carica'])->name('carica');
    });
});

