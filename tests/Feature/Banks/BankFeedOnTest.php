<?php

declare(strict_types=1);

use App\Enums\BankConnectionStatus;
use App\Models\BankConnection;
use App\Models\Family;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(fn () => CurrentFamily::clear());

test('the families with the link to the bank on are the ones turned on and not blocked', function (): void {
    $on = Family::factory()->create(['bank_feed_enabled' => true]);
    $off = Family::factory()->create(['bank_feed_enabled' => false]);
    $suspended = Family::factory()->create(['bank_feed_enabled' => true, 'suspended_at' => now()]);
    $leaving = Family::factory()->create(['bank_feed_enabled' => true, 'delete_after' => now()->addDays(30)]);

    expect(Family::query()->bankFeedOn()->pluck('id')->all())->toBe([$on->id])
        ->and($on->hasBankFeedOn())->toBeTrue()
        ->and($off->hasBankFeedOn())->toBeFalse()
        ->and($suspended->hasBankFeedOn())->toBeFalse()
        ->and($leaving->hasBankFeedOn())->toBeFalse();
});

test('the connections that can be read are the working ones and the ones with errors, not the ones to connect again', function (): void {
    $family = Family::factory()->create();
    CurrentFamily::set($family->id);
    $active = BankConnection::factory()->for($family)->create(['status' => BankConnectionStatus::Active]);
    $error = BankConnection::factory()->for($family)->create(['status' => BankConnectionStatus::Error]);
    $reconnect = BankConnection::factory()->for($family)->create(['status' => BankConnectionStatus::NeedsReconnect]);

    expect(BankConnection::query()->readable()->orderBy('id')->pluck('id')->all())->toBe([$active->id, $error->id])
        ->and($active->isReadable())->toBeTrue()
        ->and($error->isReadable())->toBeTrue()
        ->and($reconnect->isReadable())->toBeFalse();
});
