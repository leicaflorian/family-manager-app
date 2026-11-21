<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Family;
use App\Models\User;
use App\Models\UtilityConsumption;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    CurrentFamily::set($this->family->id);
    $this->row = UtilityConsumption::factory()->for($this->family)->create();
});

afterEach(fn () => CurrentFamily::clear());

test('a member sees, creates and edits consumptions but does not delete them', function (): void {
    $member = User::factory()->for($this->family)->create(['role' => Role::Member]);

    expect(Gate::forUser($member)->allows('viewAny', UtilityConsumption::class))->toBeTrue()
        ->and(Gate::forUser($member)->allows('view', $this->row))->toBeTrue()
        ->and(Gate::forUser($member)->allows('create', UtilityConsumption::class))->toBeTrue()
        ->and(Gate::forUser($member)->allows('update', $this->row))->toBeTrue()
        ->and(Gate::forUser($member)->allows('delete', $this->row))->toBeFalse();
});

test('an admin deletes a consumption', function (): void {
    $admin = User::factory()->for($this->family)->create(['role' => Role::Admin]);

    expect(Gate::forUser($admin)->allows('delete', $this->row))->toBeTrue();
});
