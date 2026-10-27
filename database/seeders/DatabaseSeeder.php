<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The test data of the app: the Bianchi family of the canvas.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Fills the database with the test data.
     */
    public function run(): void
    {
        $this->call(BianchiFamilySeeder::class);
    }
}
