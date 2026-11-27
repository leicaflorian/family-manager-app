<?php

declare(strict_types=1);

use App\Enums\BankFilterAction;
use App\Models\BankFilter;
use App\Models\Family;
use App\Models\User;

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    $this->admin = User::factory()->for($this->family)->admin()->create();
    $this->member = User::factory()->for($this->family)->create();
});

test('everyone of the family sees the filters', function (): void {
    $filter = BankFilter::factory()->for($this->family)->create();

    expect($this->member->can('viewAny', BankFilter::class))->toBeTrue()
        ->and($this->member->can('view', $filter))->toBeTrue();
});

test('a member creates a filter that creates the expense', function (): void {
    $filter = BankFilter::factory()->for($this->family)->create(['action' => BankFilterAction::Create]);

    expect($this->member->can('create', [BankFilter::class, BankFilterAction::Create]))->toBeTrue()
        ->and($this->member->can('create', [BankFilter::class, BankFilterAction::Transfer]))->toBeTrue()
        ->and($this->member->can('create', [BankFilter::class, BankFilterAction::Ignore]))->toBeTrue()
        ->and($this->member->can('update', $filter))->toBeTrue()
        ->and($this->member->can('delete', $filter))->toBeTrue();
});

test('a member cannot create, change or delete one that pays a due date', function (): void {
    $filter = BankFilter::factory()->for($this->family)->pays()->create();

    expect($this->member->can('create', [BankFilter::class, BankFilterAction::Pay]))->toBeFalse()
        ->and($this->member->can('create', BankFilter::class))->toBeFalse()
        ->and($this->member->can('update', $filter))->toBeFalse()
        ->and($this->member->can('delete', $filter))->toBeFalse();
});

test('an admin can do everything', function (): void {
    $filter = BankFilter::factory()->for($this->family)->pays()->create();

    expect($this->admin->can('create', [BankFilter::class, BankFilterAction::Pay]))->toBeTrue()
        ->and($this->admin->can('update', $filter))->toBeTrue()
        ->and($this->admin->can('delete', $filter))->toBeTrue();
});

test('a member does not touch a filter of another family', function (): void {
    $other = BankFilter::factory()->for(Family::factory()->create())->create(['action' => BankFilterAction::Create]);

    expect($this->member->can('update', $other))->toBeFalse()
        ->and($this->member->can('delete', $other))->toBeFalse();
});
