<?php

declare(strict_types=1);

use App\Actions\Families\KeepChecklistDone;
use App\Models\Family;
use Illuminate\Support\Facades\DB;

test('a family welcomed before the release keeps the checklist done', function (): void {
    $user = registerFamily();
    $family = $user->family;
    $family->forceFill(['welcomed_at' => now()->subMonth()])->save();

    resolve(KeepChecklistDone::class)();

    $home = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props']['home'];

    expect($home['checklist'])->toBe([]);
});

test('it writes the settings of a welcomed family that has none, and leaves a family not welcomed yet', function (): void {
    $welcomed = Family::factory()->create(['welcomed_at' => now()]);
    $waiting = Family::factory()->create(['welcomed_at' => null]);

    resolve(KeepChecklistDone::class)();

    $done = fn (Family $family): ?string => DB::table('family_settings')->where('family_id', $family->id)->value('checklist_done');

    expect(json_decode((string) $done($welcomed), true))->toBe(['people', 'utilities', 'first_bill', 'card', 'mail', 'invite'])
        ->and(DB::table('family_settings')->where('family_id', $waiting->id)->exists())->toBeFalse();
});

test('a family not welcomed yet still gets the checklist', function (): void {
    $user = registerFamily();
    $user->family->forceFill(['welcomed_at' => null])->save();

    resolve(KeepChecklistDone::class)();

    $user->family->forceFill(['welcomed_at' => now()])->save();
    $home = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props']['home'];

    expect($home['checklist'])->not->toBe([]);
});

test('it keeps the steps a family already has remembered', function (): void {
    $family = Family::factory()->create(['welcomed_at' => now()]);
    DB::table('family_settings')->insert(['family_id' => $family->id, 'checklist_done' => json_encode(['card']), 'created_at' => now(), 'updated_at' => now()]);

    resolve(KeepChecklistDone::class)();

    expect(json_decode((string) DB::table('family_settings')->where('family_id', $family->id)->value('checklist_done'), true))->toBe(['card']);
});
