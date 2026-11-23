<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\FamilySetting;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->family = Family::factory()->create();
    $this->other = Family::factory()->create();
});

afterEach(fn () => CurrentFamily::clear());

test('the settings of the current family are created the first time', function (): void {
    CurrentFamily::set($this->family->id);

    $settings = FamilySetting::current();

    expect($settings->exists)->toBeTrue()
        ->and($settings->family_id)->toBe($this->family->id)
        ->and(FamilySetting::query()->count())->toBe(1);
});

test('the settings already there are the ones given, never the ones of another family', function (): void {
    $theirs = CurrentFamily::using($this->other->id, fn (): FamilySetting => FamilySetting::query()->create(['mail_inbox_recipient' => 'other@example.com']));
    CurrentFamily::set($this->family->id);
    $ours = FamilySetting::query()->create([]);

    expect(FamilySetting::current()->id)->toBe($ours->id)
        ->and(FamilySetting::current()->id)->not->toBe($theirs->id);
});
