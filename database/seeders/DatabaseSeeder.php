<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * I dati di prova dell'app: la famiglia Bianchi della tela.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Riempie il database con i dati di prova.
     */
    public function run(): void
    {
        $this->call(FamigliaBianchiSeeder::class);
    }
}
