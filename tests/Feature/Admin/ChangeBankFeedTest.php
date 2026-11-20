<?php

declare(strict_types=1);

use App\Actions\Admin\ChangeBankFeed;
use App\Enums\OwnerActionType;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Models\Family;
use App\Models\Owner;
use App\Models\OwnerAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('the owner turns the bank feed on and the log has a line', function (): void {
    $family = Family::factory()->create();
    actingAs(Owner::factory()->withAuthenticator()->create(), 'owner');

    Livewire::test(ViewFamily::class, ['record' => $family->getKey()])
        ->callAction('editBankFeed', ['bank_feed_enabled' => true])
        ->assertHasNoActionErrors();

    expect($family->fresh()?->bank_feed_enabled)->toBeTrue()
        ->and(OwnerAction::query()->where('family_id', $family->id)->where('action', OwnerActionType::BankFeedOn)->count())->toBe(1);
});

test('the same value writes no line', function (): void {
    $family = Family::factory()->create();

    resolve(ChangeBankFeed::class)->handle($family, false);

    expect(OwnerAction::query()->whereIn('action', [OwnerActionType::BankFeedOn, OwnerActionType::BankFeedOff])->count())->toBe(0);
});

test('turning the bank feed off says so in the log', function (): void {
    $family = Family::factory()->create(['bank_feed_enabled' => true]);

    resolve(ChangeBankFeed::class)->handle($family, false);

    expect(OwnerAction::query()->where('family_id', $family->id)->sole()->action)->toBe(OwnerActionType::BankFeedOff);
});
