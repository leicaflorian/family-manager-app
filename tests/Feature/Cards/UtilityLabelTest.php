<?php

declare(strict_types=1);

use App\Enums\UtilityType;
use App\Models\Home;
use App\Models\Organization;
use App\Models\Utility;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(BianchiFamilySeeder::class);
});

test('a utility is named by its supplier', function (): void {
    insideBianchi(function (): void {
        $edison = Organization::query()->where('name', 'Edison')->sole();
        $home = Home::query()->where('name', 'Casa di via Roma')->sole();

        $withSupplier = Utility::query()->create(['type' => UtilityType::Electricity, 'organization_id' => $edison->id, 'home_id' => $home->id]);
        $withHomeOnly = Utility::query()->create(['type' => UtilityType::Water, 'home_id' => $home->id]);
        $withNeither = Utility::query()->create(['type' => UtilityType::Gas]);

        expect($withSupplier->label())->toBe('Electricity - Edison')
            ->and($withHomeOnly->label())->toBe('Water - Casa di via Roma')
            ->and($withNeither->label())->toBe('Gas')
            ->and(Utility::labelRelations())->toBe(['organization', 'home']);
    });
});
