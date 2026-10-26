<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Family;
use App\Models\FamilySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilySetting>
 */
final class FamilySettingFactory extends Factory
{
    /**
     * Le impostazioni di una famiglia.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
        ];
    }
}
