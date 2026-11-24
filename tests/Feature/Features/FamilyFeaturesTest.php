<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\UtilityType;
use App\Models\DocumentCategory;
use App\Models\Family;
use App\Models\User;
use App\Models\Utility;
use App\Services\FamilyFeatures;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Route;

it('says the bank feed is on only with an active provider and the switch of the family', function (): void {
    $family = Family::factory()->create(['bank_feed_enabled' => true]);
    $features = resolve(FamilyFeatures::class);

    config(['bank_feed.provider' => '']);
    expect($features->for($family, Feature::BankFeed))->toBeFalse();

    config(['bank_feed.provider' => 'fake']);
    expect($features->for($family, Feature::BankFeed))->toBeTrue();

    CurrentFamily::using($family->id, function () use ($features): void {
        expect($features->on(Feature::BankFeed))->toBeTrue();
    });
});

it('reads the current family again after it was saved', function (): void {
    config(['bank_feed.provider' => 'fake']);
    $family = Family::factory()->create(['bank_feed_enabled' => true]);
    $features = resolve(FamilyFeatures::class);

    CurrentFamily::using($family->id, function () use ($family, $features): void {
        expect($features->on(Feature::BankFeed))->toBeTrue();

        $family->forceFill(['bank_feed_enabled' => false])->save();

        expect($features->on(Feature::BankFeed))->toBeFalse();
    });
});

it('keeps the value of each family apart', function (): void {
    config(['bank_feed.provider' => 'fake']);
    $on = Family::factory()->create(['bank_feed_enabled' => true]);
    $off = Family::factory()->create(['bank_feed_enabled' => false]);
    $features = resolve(FamilyFeatures::class);

    $seen = [];
    foreach ([$on, $off, $on] as $family) {
        $seen[] = CurrentFamily::using($family->id, fn (): bool => $features->on(Feature::BankFeed));
    }

    expect($seen)->toBe([true, false, true]);
});

it('says there are no features without a family', function (): void {
    expect(resolve(FamilyFeatures::class)->on(Feature::BankFeed))->toBeFalse();
});

it('has the gallery and the consumption only with the data they need', function (): void {
    $family = Family::factory()->create();
    $features = resolve(FamilyFeatures::class);

    expect($features->for($family, Feature::Gallery))->toBeFalse()
        ->and($features->for($family, Feature::Consumption))->toBeFalse();

    CurrentFamily::using($family->id, static function () use ($family): void {
        DocumentCategory::factory()->create(['family_id' => $family->id, 'gallery' => true]);
        Utility::factory()->create(['family_id' => $family->id, 'type' => UtilityType::Electricity]);
    });

    expect($features->for($family, Feature::Gallery))->toBeTrue()
        ->and($features->for($family, Feature::Consumption))->toBeTrue()
        ->and($features->all($family))->toBe(['budget' => true, 'mail' => true, 'bank_feed' => false, 'consumption' => true, 'gallery' => true]);
});

it('reads the mail domain trimmed, null when empty', function (): void {
    $features = resolve(FamilyFeatures::class);

    config(['services.mailgun.inbox_domain' => ' mg.example.test ']);
    expect($features->mailDomain())->toBe('mg.example.test');

    config(['services.mailgun.inbox_domain' => '  ']);
    expect($features->mailDomain())->toBeNull();

    config(['services.mailgun.inbox_domain' => null]);
    expect($features->mailDomain())->toBeNull();
});

it('lets a route answer 404 while the feature is off', function (): void {
    config(['bank_feed.provider' => 'fake']);
    Route::middleware(['web', 'auth', 'feature:bank_feed'])->get('/__feature-probe', fn (): string => 'ok');
    $user = User::factory()->create();

    $user->family->forceFill(['bank_feed_enabled' => false])->save();
    $this->actingAs($user)->get('/__feature-probe')->assertNotFound();

    $user->family->forceFill(['bank_feed_enabled' => true])->save();
    $this->actingAs($user)->get('/__feature-probe')->assertOk();
});
