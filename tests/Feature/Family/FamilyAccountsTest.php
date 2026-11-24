<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    $this->other = Family::factory()->create();
    $this->member = User::factory()->for($this->family)->create();
    $this->admin = User::factory()->for($this->family)->admin()->create();
    $this->stranger = User::factory()->for($this->other)->admin()->create();
});

afterEach(fn () => CurrentFamily::clear());

test('the accounts of a family, by id, without the other families', function (): void {
    expect(User::query()->inFamily($this->family->id)->pluck('id')->all())->toBe([$this->member->id, $this->admin->id]);
});

test('the admins of a family', function (): void {
    expect(User::query()->inFamily($this->family->id)->admins()->pluck('id')->all())->toBe([$this->admin->id]);
});

test('the accounts of the current family come with their person', function (): void {
    CurrentFamily::set($this->family->id);
    Person::factory()->for($this->family)->create(['first_name' => 'Marco', 'user_id' => $this->admin->id]);

    $accounts = User::ofCurrentFamily();

    expect($accounts->pluck('id')->all())->toBe([$this->member->id, $this->admin->id])
        ->and($accounts[1]->relationLoaded('person'))->toBeTrue()
        ->and($accounts[1]->person?->first_name)->toBe('Marco');
});
