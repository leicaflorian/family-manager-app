<?php

declare(strict_types=1);

use App\Models\Family;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The migration is run by hand, down and up again: MySQL commits the transaction of RefreshDatabase at the first
 * change of a table, and Laravel then migrates the database again for the next test.
 */
beforeEach(function (): void {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_11_13_000001_add_card_type_to_document_categories.php');
    $migration->down();

    $this->a = Family::factory()->create();
    $this->b = Family::factory()->create();

    $make = fn (Family $family, string $name, array $extra = []): int => DB::table('document_categories')->insertGetId(['family_id' => $family->id, 'name' => $name, ...$extra]);
    $this->english = $make($this->a, 'Home and utilities');
    $this->italian = $make($this->b, 'Veicoli');
    $this->sub = $make($this->b, 'Buste paga');
    $this->renamed = $make($this->a, 'Our cars');
    $this->bank = $make($this->a, 'Banks and loans');

    $migration->up();
});

it('gives the starter categories their card, in English and in Italian', function (): void {
    expect(DB::table('document_categories')->find($this->english)->card_type)->toBe('utility')
        ->and(DB::table('document_categories')->find($this->italian)->card_type)->toBe('vehicle')
        ->and(DB::table('document_categories')->find($this->sub)->card_type)->toBe('employment');
});

it('leaves a renamed category and one without a card as they are', function (): void {
    expect(DB::table('document_categories')->find($this->renamed)->card_type)->toBeNull()
        ->and(DB::table('document_categories')->find($this->bank)->card_type)->toBeNull();
});

it('does not change a category that already has a card', function (): void {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_11_13_000001_add_card_type_to_document_categories.php');
    DB::table('document_categories')->where('id', $this->english)->update(['card_type' => 'home']);

    $migration->up();

    expect(DB::table('document_categories')->find($this->english)->card_type)->toBe('home');
});

it('goes down also when the column is already gone', function (): void {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_11_13_000001_add_card_type_to_document_categories.php');
    $migration->down();
    $migration->down();

    expect(Schema::hasColumn('document_categories', 'card_type'))->toBeFalse();

    $migration->up();
});
