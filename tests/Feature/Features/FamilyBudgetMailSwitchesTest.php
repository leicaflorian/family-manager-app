<?php

declare(strict_types=1);

use App\Actions\Families\CreateFamily;
use App\Enums\Feature;
use App\Enums\Role;
use App\Enums\UtilityType;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Models\Family;
use App\Models\Owner;
use App\Models\User;
use App\Models\Utility;
use App\Services\FamilyFeatures;
use App\Support\CurrentFamily;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('turns on budget and mail for the families that exist when the migration runs', function (): void {
    $family = Family::factory()->withoutFeatures()->create();
    expect($family->fresh()?->budget_enabled)->toBeFalse();

    DB::table('families')->update(['budget_enabled' => false, 'mail_enabled' => false]);

    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_11_24_000001_add_features_to_families.php');
    $migration->down();
    $migration->up();

    $fresh = $family->fresh();
    expect($fresh?->budget_enabled)->toBeTrue()->and($fresh?->mail_enabled)->toBeTrue();
});

it('creates a family with budget and mail off', function (): void {
    $user = resolve(CreateFamily::class)->handle('Rossi', 'Anna', 'Rossi', 'anna@example.com', 'password-long-enough');

    $family = Family::query()->findOrFail($user->family_id);
    expect($family->budget_enabled)->toBeFalse()->and($family->mail_enabled)->toBeFalse();
});

it('has budget and mail on in a factory family, and off with withoutFeatures', function (): void {
    expect(Family::factory()->create()->budget_enabled)->toBeTrue()
        ->and(Family::factory()->create()->mail_enabled)->toBeTrue()
        ->and(Family::factory()->withoutFeatures()->create()->budget_enabled)->toBeFalse();
});

it('lets only an admin of the same family manage the features', function (): void {
    $family = Family::factory()->create();
    $admin = User::factory()->create(['family_id' => $family->id, 'role' => Role::Admin]);
    $member = User::factory()->create(['family_id' => $family->id, 'role' => Role::Member]);
    $other = User::factory()->create(['family_id' => Family::factory()->create()->id, 'role' => Role::Admin]);

    expect(Gate::forUser($admin)->allows('manageFeatures', $family))->toBeTrue()
        ->and(Gate::forUser($member)->allows('manageFeatures', $family))->toBeFalse()
        ->and(Gate::forUser($other)->allows('manageFeatures', $family))->toBeFalse();
});

it('reads the budget and mail switches, and mail needs the domain', function (): void {
    config(['services.mailgun.inbox_domain' => 'example.com']);
    $on = Family::factory()->create();
    $off = Family::factory()->withoutFeatures()->create();
    $features = resolve(FamilyFeatures::class);

    expect($features->for($on, Feature::Budget))->toBeTrue()
        ->and($features->for($on, Feature::Mail))->toBeTrue()
        ->and($features->for($off, Feature::Budget))->toBeFalse()
        ->and($features->for($off, Feature::Mail))->toBeFalse();

    CurrentFamily::using($on->id, function () use ($features): void {
        expect($features->on(Feature::Budget))->toBeTrue()->and($features->on(Feature::Mail))->toBeTrue();
    });

    config(['services.mailgun.inbox_domain' => '']);
    expect($features->for($on, Feature::Mail))->toBeFalse();
});

it('turns the bank feed and consumption off with the budget, even when switched on', function (): void {
    config(['bank_feed.provider' => 'fake']);
    $family = Family::factory()->create(['bank_feed_enabled' => true]);
    $features = resolve(FamilyFeatures::class);

    CurrentFamily::using($family->id, function () use ($features, $family): void {
        Utility::factory()->create(['family_id' => $family->id, 'type' => UtilityType::Electricity]);
        expect($features->on(Feature::BankFeed))->toBeTrue()->and($features->on(Feature::Consumption))->toBeTrue();
    });

    $family->forceFill(['budget_enabled' => false])->save();

    CurrentFamily::using($family->id, function () use ($features, $family): void {
        expect($features->on(Feature::BankFeed))->toBeFalse()
            ->and($features->on(Feature::Consumption))->toBeFalse()
            ->and($features->for($family->fresh() ?? $family, Feature::BankFeed))->toBeFalse();
    });
});

it('shows in the panel whether budget and mail are turned on', function (): void {
    $on = Family::factory()->create();
    $off = Family::factory()->withoutFeatures()->create();
    actingAs(Owner::factory()->withAuthenticator()->create(), 'owner');

    Livewire::test(ViewFamily::class, ['record' => $on->getKey()])
        ->assertSee('Budget')->assertSee('Mail for the bills')->assertSee('Turned on');

    Livewire::test(ViewFamily::class, ['record' => $off->getKey()])
        ->assertSee('Mail for the bills')->assertSee('Turned off');
});
