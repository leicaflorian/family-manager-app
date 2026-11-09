<?php

declare(strict_types=1);

use App\Actions\Admin\CancelFamilyDeletion;
use App\Actions\Admin\ScheduleFamilyDeletion;
use App\Actions\Admin\SuspendFamily;
use App\Enums\FamilyState;
use App\Enums\OwnerActionType;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Models\AiReading;
use App\Models\Family;
use App\Models\Owner;
use App\Models\OwnerAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->owner = Owner::factory()->withAuthenticator()->create();
    $this->rossi = Family::factory()->create(['name' => 'Rossi']);
    $this->verdi = Family::factory()->create(['name' => 'Verdi']);
    User::factory()->for($this->rossi)->create();
    User::factory()->for($this->verdi)->create();
    Storage::disk('local')->put("families/{$this->rossi->id}/a.pdf", 'a');
    Storage::disk('local')->put("families/{$this->verdi->id}/b.pdf", 'b');
});

test('deletion waits 30 days', function (): void {
    resolve(ScheduleFamilyDeletion::class)->handle($this->rossi);

    expect($this->rossi->refresh()->state())->toBe(FamilyState::ToDelete)
        ->and($this->rossi->suspended_at)->not->toBeNull()
        ->and($this->rossi->delete_after?->toDateString())->toBe(now()->addDays(30)->toDateString());

    $this->artisan('model:prune', ['--model' => [Family::class]])->assertSuccessful();
    expect(Family::query()->whereKey($this->rossi->id)->exists())->toBeTrue();

    $this->travel(31)->days();
    $this->artisan('model:prune', ['--model' => [Family::class]])->assertSuccessful();

    expect(Family::query()->whereKey($this->rossi->id)->exists())->toBeFalse()
        ->and(User::query()->where('family_id', $this->rossi->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists("families/{$this->rossi->id}/a.pdf"))->toBeFalse();
});

test('cancel keeps the suspension of a family suspended before the deletion was scheduled', function (): void {
    resolve(SuspendFamily::class)->handle($this->rossi);
    $suspendedAt = $this->rossi->refresh()->suspended_at;

    $this->travel(3)->days();
    resolve(ScheduleFamilyDeletion::class)->handle($this->rossi);
    resolve(CancelFamilyDeletion::class)->handle($this->rossi->refresh());

    expect($this->rossi->refresh()->state())->toBe(FamilyState::Suspended)
        ->and($this->rossi->suspended_at?->toDateTimeString())->toBe($suspendedAt?->toDateTimeString())
        ->and($this->rossi->delete_after)->toBeNull();
});

test('cancel makes active a family that the deletion suspended', function (): void {
    resolve(ScheduleFamilyDeletion::class)->handle($this->rossi);

    $this->travel(5)->days();
    resolve(CancelFamilyDeletion::class)->handle($this->rossi->refresh());

    expect($this->rossi->refresh()->state())->toBe(FamilyState::Active)
        ->and($this->rossi->suspended_at)->toBeNull();
});

test('cancel keeps the family', function (): void {
    resolve(ScheduleFamilyDeletion::class)->handle($this->rossi);
    resolve(CancelFamilyDeletion::class)->handle($this->rossi->refresh());

    expect($this->rossi->refresh()->state())->toBe(FamilyState::Active);

    $this->travel(31)->days();
    $this->artisan('model:prune', ['--model' => [Family::class]])->assertSuccessful();

    expect(Family::query()->whereKey($this->rossi->id)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists("families/{$this->rossi->id}/a.pdf"))->toBeTrue();
});

test('pruning leaves the other families alone', function (): void {
    resolve(ScheduleFamilyDeletion::class)->handle($this->rossi);
    $this->travel(31)->days();
    $this->artisan('model:prune', ['--model' => [Family::class]])->assertSuccessful();

    expect(Family::query()->whereKey($this->verdi->id)->exists())->toBeTrue()
        ->and(User::query()->where('family_id', $this->verdi->id)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists("families/{$this->verdi->id}/b.pdf"))->toBeTrue();
});

test('the delete button needs the exact name', function (): void {
    actingAs($this->owner, 'owner');

    Livewire::test(ViewFamily::class, ['record' => $this->rossi->getRouteKey()])
        ->callAction('delete', ['name' => 'rossi'])
        ->assertHasActionErrors(['name']);

    expect($this->rossi->refresh()->delete_after)->toBeNull();

    Livewire::test(ViewFamily::class, ['record' => $this->rossi->getRouteKey()])
        ->callAction('delete', ['name' => 'Rossi'])
        ->assertHasNoActionErrors();

    expect($this->rossi->refresh()->state())->toBe(FamilyState::ToDelete);

    Livewire::test(ViewFamily::class, ['record' => $this->rossi->getRouteKey()])
        ->assertActionHidden('delete')
        ->callAction('cancelDeletion');

    expect($this->rossi->refresh()->state())->toBe(FamilyState::Active);
});

test('the log keeps the name of a deleted family and the costs stay', function (): void {
    AiReading::factory()->create(['family_id' => $this->rossi->id]);
    actingAs($this->owner, 'owner');
    resolve(ScheduleFamilyDeletion::class)->handle($this->rossi);

    $this->travel(31)->days();
    $this->artisan('model:prune', ['--model' => [Family::class]])->assertSuccessful();

    $row = OwnerAction::query()->sole();
    expect($row->action)->toBe(OwnerActionType::DeletionScheduled)
        ->and($row->family_name)->toBe('Rossi')
        ->and($row->family_id)->toBe($this->rossi->id)
        ->and(DB::table('ai_readings')->whereNull('family_id')->count())->toBe(1);
});
