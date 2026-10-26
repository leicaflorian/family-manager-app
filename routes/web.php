<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('aree', 'Aree')->name('aree');

    // Le pagine che arrivano con i prossimi task: le voci del menu portano già qui.
    Route::inertia('documenti', 'InArrivo', ['titolo' => 'Documenti'])->name('documenti.index');
    Route::inertia('famiglia', 'InArrivo', ['titolo' => 'Famiglia'])->name('famiglia');
    Route::inertia('carica', 'InArrivo', ['titolo' => 'Carica'])->name('carica');
    Route::inertia('impostazioni', 'InArrivo', ['titolo' => 'Impostazioni'])->name('impostazioni');
});

require __DIR__.'/settings.php';
