<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\ReminderNotice;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    $this->marco = User::factory()->for($this->family)->create();
    $this->laura = User::factory()->for($this->family)->create();
});

/**
 * The headers of a partial reload of the bell notices from a page.
 *
 * @return array<string, string>
 */
function bellReload(string $component): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => 'bell_notices',
    ];
}

test('a visit to a page does not carry the notices of the bell', function (): void {
    $this->actingAs($this->marco)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Home')
        ->missing('bell_notices')
        ->missing('notices'));
});

test('a partial reload from another page brings the new and seen notices of the account only', function (): void {
    $new = ReminderNotice::factory()->for($this->family)->create(['user_id' => $this->marco->id]);
    $seen = ReminderNotice::factory()->for($this->family)->create(['user_id' => $this->marco->id, 'read_at' => now()]);
    ReminderNotice::factory()->for($this->family)->create(['user_id' => $this->laura->id]);

    $props = $this->actingAs($this->marco)
        ->get(route('areas'), bellReload('Areas'))
        ->assertOk()
        ->json('props');

    expect(collect($props['bell_notices']['unread'])->pluck('id')->all())->toBe([$new->id])
        ->and(collect($props['bell_notices']['seen'])->pluck('id')->all())->toBe([$seen->id]);
});

test('a phone linked with a code does not receive the notices of the bell', function (): void {
    $device = LinkedDevice::factory()->for($this->family)->for($this->marco)->create(['token_hash' => hash('sha256', 'x'), 'last_used_at' => now()]);

    $response = $this->actingAs($this->marco)->withSession(['linked_device_id' => $device->id])
        ->get(route('scan'), bellReload('scan/Scan'));

    expect($response->json('props') ?? [])->not->toHaveKey('bell_notices');
});
