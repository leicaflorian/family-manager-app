<?php

declare(strict_types=1);

use App\Actions\Devices\ForgetLinkedDevices;
use App\Models\Family;
use App\Models\LinkedDevice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    $this->family = Family::factory()->create();
    $this->laura = User::factory()->for($this->family)->create(['password' => 'a-long-password']);
    $this->marco = User::factory()->for($this->family)->create();
});

/** Writes a row of `sessions` the way the database driver does. */
function writeSession(string $id, ?int $userId): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
}

test('findByToken finds a phone with the right secret and not with another', function (): void {
    $phone = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['token_hash' => hash('sha256', 'secret-1')]);

    // Outside the family scope: nobody is signed in
    expect(LinkedDevice::findByToken('secret-1')?->is($phone))->toBeTrue()
        ->and(LinkedDevice::findByToken('secret-2'))->toBeNull();
});

test('a phone without entries for 181 days is not found, one of 179 days is', function (): void {
    $old = LinkedDevice::factory()->for($this->family)->for($this->laura)
        ->create(['token_hash' => hash('sha256', 'old'), 'last_used_at' => null, 'created_at' => now()->subDays(181)]);
    $recent = LinkedDevice::factory()->for($this->family)->for($this->laura)
        ->create(['token_hash' => hash('sha256', 'recent'), 'last_used_at' => null, 'created_at' => now()->subDays(179)]);
    $usedAgain = LinkedDevice::factory()->for($this->family)->for($this->laura)
        ->create(['token_hash' => hash('sha256', 'again'), 'created_at' => now()->subDays(400), 'last_used_at' => now()->subDays(10)]);

    expect(LinkedDevice::findByToken('old'))->toBeNull()
        ->and(LinkedDevice::findByToken('recent')?->is($recent))->toBeTrue()
        ->and(LinkedDevice::findByToken('again')?->is($usedAgain))->toBeTrue()
        ->and(LinkedDevice::EXPIRES_AFTER_DAYS)->toBe(180);
});

test('model:prune removes the expired phones and keeps the others', function (): void {
    $expired = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['last_used_at' => now()->subDays(181)]);
    $kept = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['last_used_at' => now()->subDays(179)]);

    $this->artisan('model:prune', ['--model' => [LinkedDevice::class]])->assertSuccessful();

    expect(LinkedDevice::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$kept->id])
        ->and(LinkedDevice::query()->withoutGlobalScopes()->whereKey($expired->id)->exists())->toBeFalse();
});

test('model:prune of the schedule includes the linked phones', function (): void {
    $this->artisan('schedule:list')->expectsOutputToContain('model:prune')->assertSuccessful();
    expect(file_get_contents(base_path('routes/console.php')))->toContain('LinkedDevice::class');
});

test('ForgetLinkedDevices removes all the phones of the account with their sessions', function (): void {
    $a = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['session_id' => 'sess-a']);
    LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['session_id' => 'sess-b']);
    $other = LinkedDevice::factory()->for($this->family)->for($this->marco)->create(['session_id' => 'sess-m']);
    writeSession('sess-a', $this->laura->id);
    writeSession('sess-b', $this->laura->id);
    writeSession('sess-m', $this->marco->id);

    $count = resolve(ForgetLinkedDevices::class)->handle($this->laura);

    expect($count)->toBe(2)
        ->and(LinkedDevice::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$other->id])
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['sess-m'])
        ->and(LinkedDevice::query()->withoutGlobalScopes()->whereKey($a->id)->exists())->toBeFalse();
});

test('ForgetLinkedDevices can forget only one phone', function (): void {
    $a = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['session_id' => 'sess-a']);
    $b = LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['session_id' => 'sess-b']);
    writeSession('sess-a', $this->laura->id);
    writeSession('sess-b', $this->laura->id);

    expect(resolve(ForgetLinkedDevices::class)->handle($this->laura, $a))->toBe(1)
        ->and(LinkedDevice::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$b->id])
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['sess-b']);
});

test('ForgetLinkedDevices works for a phone that has no session yet', function (): void {
    LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['session_id' => null]);

    expect(resolve(ForgetLinkedDevices::class)->handle($this->laura))->toBe(1);
});

test('changing the password from My account forgets the phones of the account only', function (): void {
    LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['session_id' => 'sess-l']);
    $other = LinkedDevice::factory()->for($this->family)->for($this->marco)->create(['session_id' => 'sess-m']);
    writeSession('sess-l', $this->laura->id);
    writeSession('sess-m', $this->marco->id);

    $this->actingAs($this->laura)
        ->put(route('account.password'), ['current_password' => 'a-long-password', 'password' => 'a-new-password'])
        ->assertSessionHasNoErrors();

    expect(LinkedDevice::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$other->id])
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['sess-m']);
});

test('the Fortify reset forgets the phones of the account only', function (): void {
    LinkedDevice::factory()->for($this->family)->for($this->laura)->create(['session_id' => 'sess-l']);
    $other = LinkedDevice::factory()->for($this->family)->for($this->marco)->create(['session_id' => 'sess-m']);
    writeSession('sess-l', $this->laura->id);
    writeSession('sess-m', $this->marco->id);

    $this->post(route('password.update'), [
        'token' => Password::broker()->createToken($this->laura),
        'email' => $this->laura->email,
        'password' => 'another-new-password',
        'password_confirmation' => 'another-new-password',
    ])->assertSessionHasNoErrors();

    expect(LinkedDevice::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$other->id])
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['sess-m']);
});

test('changing something other than the password keeps the phones', function (): void {
    LinkedDevice::factory()->for($this->family)->for($this->laura)->create();

    $this->laura->update(['name' => 'Laura B.']);

    expect(LinkedDevice::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('deleting the account removes its phones', function (): void {
    LinkedDevice::factory()->for($this->family)->for($this->laura)->create();
    $other = LinkedDevice::factory()->for($this->family)->for($this->marco)->create();

    $this->laura->delete();

    expect(LinkedDevice::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$other->id]);
});

test('the user has linked phones', function (): void {
    $phone = LinkedDevice::factory()->for($this->family)->for($this->laura)->create();

    $this->actingAs($this->laura);

    expect($this->laura->linkedDevices->pluck('id')->all())->toBe([$phone->id]);
});

test('the policy lets everyone list and only the owner of the phone delete it', function (): void {
    $phone = LinkedDevice::factory()->for($this->family)->for($this->laura)->create();
    $admin = User::factory()->for($this->family)->create(['role' => 'admin']);

    expect($this->laura->can('viewAny', LinkedDevice::class))->toBeTrue()
        ->and($this->marco->can('viewAny', LinkedDevice::class))->toBeTrue()
        ->and($this->laura->can('delete', $phone))->toBeTrue()
        ->and($this->marco->can('delete', $phone))->toBeFalse()
        ->and($admin->can('delete', $phone))->toBeFalse()
        ->and($this->laura->can('create', LinkedDevice::class))->toBeFalse();
});
