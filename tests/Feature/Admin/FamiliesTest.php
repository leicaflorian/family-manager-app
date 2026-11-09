<?php

declare(strict_types=1);

use App\Enums\FamilyState;
use App\Enums\ReadingPurpose;
use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\LinkedDevicesRelationManager;
use App\Filament\Resources\Families\RelationManagers\UsersRelationManager;
use App\Models\AiReading;
use App\Models\Document;
use App\Models\Family;
use App\Models\File;
use App\Models\LinkedDevice;
use App\Models\Owner;
use App\Models\User;
use App\Services\Reading\ReadingAllowance;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Signs in as an owner. Called after the data of the test is made: with an owner signed in, the models
 * would take his id as their author.
 */
function asOwner(): void
{
    actingAs(Owner::factory()->withAuthenticator()->create(), 'owner');
}

it('shows every family with its figures', function (): void {
    $rossi = Family::factory()->create(['name' => 'Rossi']);
    $verdi = Family::factory()->create(['name' => 'Verdi']);
    User::factory()->count(2)->create(['family_id' => $rossi->id]);
    Document::factory()->count(3)->create(['family_id' => $rossi->id]);
    AiReading::factory()->create(['family_id' => $rossi->id, 'cost_usd' => '0.014200']);

    asOwner();

    Livewire::test(ListFamilies::class)
        ->assertCanSeeTableRecords([$rossi, $verdi])
        ->assertTableColumnStateSet('users_count', 2, $rossi)
        ->assertTableColumnStateSet('documents', 3, $rossi)
        ->assertTableColumnStateSet('readings', 1, $rossi)
        ->assertSee('0,0142 $');
});

it('shows the used space in megabytes', function (): void {
    $family = Family::factory()->create(['name' => 'Rossi']);
    File::factory()->create(['family_id' => $family->id, 'size' => 148 * 1024 * 1024]);

    asOwner();

    Livewire::test(ListFamilies::class)->assertSee('148 MB');
});

it('searches a family by the email of an account', function (): void {
    $rossi = Family::factory()->create(['name' => 'Rossi']);
    $verdi = Family::factory()->create(['name' => 'Verdi']);
    User::factory()->create(['family_id' => $rossi->id, 'email' => 'laura@example.com']);

    asOwner();

    Livewire::test(ListFamilies::class)
        ->searchTable('laura@example')
        ->assertCanSeeTableRecords([$rossi])
        ->assertCanNotSeeTableRecords([$verdi]);
});

it('says when a copied family was copied', function (): void {
    $family = Family::factory()->create(['name' => 'Rossi', 'copied_from_directus_at' => '2026-10-15 09:00:00']);

    asOwner();

    Livewire::test(ListFamilies::class)
        ->assertSee('Copied from Directus on')
        ->assertSee('15/10/2026');
});

it('orders families from the most recent', function (): void {
    $old = Family::factory()->create(['created_at' => now()->subDays(5)]);
    $new = Family::factory()->create(['created_at' => now()]);

    asOwner();

    Livewire::test(ListFamilies::class)->assertCanSeeTableRecords([$new, $old], inOrder: true);
});

it('gives the state of a family', function (): void {
    expect(Family::factory()->make()->state())->toBe(FamilyState::Active)
        ->and(Family::factory()->make(['suspended_at' => now()])->state())->toBe(FamilyState::Suspended)
        ->and(Family::factory()->make(['suspended_at' => now(), 'delete_after' => now()->addDays(30)])->state())->toBe(FamilyState::ToDelete);
});

it('shows the accounts and the linked devices on the family page', function (): void {
    $family = Family::factory()->create();
    $laura = User::factory()->admin()->create(['family_id' => $family->id, 'name' => 'Laura Bianchi', 'email' => 'laura@example.com']);
    $device = LinkedDevice::factory()->create(['family_id' => $family->id, 'user_id' => $laura->id, 'name' => 'iPhone di Laura']);

    asOwner();

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertCanSeeTableRecords([$laura])
        ->assertSee('laura@example.com');

    Livewire::test(LinkedDevicesRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertCanSeeTableRecords([$device])
        ->assertSee('iPhone di Laura');

    $this->get(FamilyResource::getUrl('view', ['record' => $family]))->assertOk();
});

it('saves the reading limit of a family and uses it', function (): void {
    $family = Family::factory()->create();
    $other = Family::factory()->create();

    asOwner();

    Livewire::test(ViewFamily::class, ['record' => $family->getKey()])
        ->callAction('editReadingLimit', ['reading_limit' => 2])
        ->assertHasNoActionErrors();

    expect($family->fresh()->reading_limit)->toBe(2);

    foreach ([$family, $other] as $current) {
        AiReading::factory()->count(2)->create(['family_id' => $current->id, 'purpose' => ReadingPurpose::Document]);
    }
    $now = CarbonImmutable::now();
    $left = fn (Family $f): int => CurrentFamily::using($f->id, fn (): int => resolve(ReadingAllowance::class)->left($now));

    expect($left($family))->toBe(0)->and($left($other))->toBe(998);
});

it('keeps the cost of a deleted family', function (): void {
    $family = Family::factory()->create();
    AiReading::factory()->count(2)->create(['family_id' => $family->id]);

    $family->delete();

    expect(DB::table('ai_readings')->count())->toBe(2)
        ->and(DB::table('ai_readings')->whereNotNull('family_id')->count())->toBe(0);
});
