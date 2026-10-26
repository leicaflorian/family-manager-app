<?php

declare(strict_types=1);

namespace App\Actions\Famiglie;

use App\Models\Family;
use App\Models\Home;
use App\Models\Person;
use App\Support\Iniziali;
use Illuminate\Support\Facades\DB;

/**
 * Chiude il *Benvenuto*: aggiunge le persone e la casa, e segna la famiglia come accolta.
 */
final class ConcludiBenvenuto
{
    /**
     * Crea le persone e la casa nella famiglia corrente e compila `welcomed_at`.
     *
     * @param  Family  $famiglia  la famiglia di chi è entrato
     * @param  list<array{first_name: string, last_name: string, role: string}>  $persone  le persone da aggiungere
     * @param  array{name: string, address?: string|null, city?: string|null, is_owned?: bool}|null  $casa  la casa, o null per dopo
     */
    public function handle(Family $famiglia, array $persone, ?array $casa): void
    {
        DB::transaction(function () use ($famiglia, $persone, $casa): void {
            foreach ($persone as $persona) {
                Person::query()->create([
                    ...$persona,
                    'initials' => Iniziali::di($persona['first_name'], $persona['last_name']),
                ]);
            }

            if ($casa !== null) {
                Home::query()->create([...$casa, 'country' => 'Italia']);
            }

            $famiglia->update(['welcomed_at' => now()]);
        });
    }
}
