<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoManutenzione;
use App\Models\Family;
use App\Models\MaintenanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRecord>
 */
final class MaintenanceRecordFactory extends Factory
{
    /**
     * Una manutenzione.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'title' => 'Tagliando',
            'date' => fake()->dateTimeBetween('-1 year'),
            'type' => TipoManutenzione::Service,
        ];
    }
}
