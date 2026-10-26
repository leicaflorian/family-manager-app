<?php

declare(strict_types=1);

use App\Enums\Ruolo;
use App\Enums\StatoDocumento;
use App\Enums\TipoEnte;
use App\Models\Utility;
use App\Support\Schede;

test('le scelte fisse hanno le etichette di Directus, nel loro ordine', function (): void {
    expect(StatoDocumento::opzioni())->toBe([
        ['value' => 'active', 'label' => 'Attivo'],
        ['value' => 'archived', 'label' => 'Archiviato'],
        ['value' => 'expired', 'label' => 'Scaduto'],
    ])
        ->and(TipoEnte::Healthcare->etichetta())->toBe('Sanità')
        ->and(Ruolo::Member->etichetta())->toBe('Vede tutto e carica i documenti');
});

test('i tipi polimorfici sono il nome al singolare della tabella', function (): void {
    expect(Schede::tipoDi(Utility::class))->toBe('utility')
        ->and(Schede::tipoDaCollection('utilities'))->toBe('utility')
        ->and(Schede::tipoDaCollection('maintenance_records'))->toBe('maintenance_record')
        ->and(Schede::tipoDaCollection('directus_users'))->toBeNull()
        ->and(array_keys(Schede::CON_DOCUMENTI))->toHaveCount(15);
});
