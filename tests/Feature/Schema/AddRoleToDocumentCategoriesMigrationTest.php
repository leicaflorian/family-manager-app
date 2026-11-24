<?php

declare(strict_types=1);

use App\Enums\DocumentCategoryRole;
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
    $migration = require database_path('migrations/2026_11_24_000004_add_role_to_document_categories_table.php');
    $migration->down();

    $this->a = Family::factory()->create();
    $this->b = Family::factory()->create();
    $this->c = Family::factory()->create();
    $this->d = Family::factory()->create();

    $user = fn (Family $family, string $locale) => DB::table('users')->insert(['family_id' => $family->id, 'name' => 'x', 'email' => "x{$family->id}@example.com", 'password' => 'x', 'role' => 'admin', 'locale' => $locale, 'created_at' => now(), 'updated_at' => now()]);
    $make = fn (Family $family, string $name, array $extra = []): int => DB::table('document_categories')->insertGetId(['family_id' => $family->id, 'name' => $name, ...$extra]);

    // A has Vehicles, with an Italian admin; B has no vehicles and an English admin; C already has the category, named in Italian
    $user($this->a, 'it');
    $user($this->b, 'en');
    $user($this->c, 'it');
    $this->aVehicles = $make($this->a, 'Veicoli', ['card_type' => 'vehicle', 'sort' => 3]);
    $this->bOther = $make($this->b, 'Work', ['sort' => 1]);
    $this->cVehicles = $make($this->c, 'Veicoli', ['card_type' => 'vehicle', 'sort' => 3]);
    $this->cExisting = $make($this->c, 'libretto di CIRCOLAZIONE', ['parent_id' => $this->cVehicles]);
    // D has no category and no account: it gets the language of the app and a first place
    $migration->up();
});

it('creates the category under Vehicles in the language of the first admin, with the role', function (): void {
    $row = DB::table('document_categories')->where('family_id', $this->a->id)->where('role', 'vehicle_registration')->sole();

    expect($row->name)->toBe('Libretto di circolazione')
        ->and($row->parent_id)->toBe($this->aVehicles)
        ->and(DocumentCategoryRole::from($row->role))->toBe(DocumentCategoryRole::VehicleRegistration);
});

it('creates it at the top of the list, in English, when the family has no Vehicles', function (): void {
    $row = DB::table('document_categories')->where('family_id', $this->b->id)->where('role', 'vehicle_registration')->sole();

    expect($row->name)->toBe('Vehicle registration document')
        ->and($row->parent_id)->toBeNull();
});

it('gives the role to a category already called so, without moving it', function (): void {
    $rows = DB::table('document_categories')->where('family_id', $this->c->id)->where('role', 'vehicle_registration')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->sole()->id)->toBe($this->cExisting)
        ->and($rows->sole()->parent_id)->toBe($this->cVehicles)
        ->and(DB::table('document_categories')->where('family_id', $this->c->id)->count())->toBe(2);
});

it('gives one category per family, in the language of the app to a family without an account', function (): void {
    expect(DB::table('document_categories')->where('role', 'vehicle_registration')->count())->toBe(4)
        ->and(DB::table('document_categories')->where('family_id', $this->d->id)->sole()->name)->toBe('Vehicle registration document');
});

it('can run twice without making a second one', function (): void {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_11_24_000004_add_role_to_document_categories_table.php');
    $migration->up();

    expect(DB::table('document_categories')->where('role', 'vehicle_registration')->count())->toBe(4);
});

it('goes down also when the column is already gone', function (): void {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_11_24_000004_add_role_to_document_categories_table.php');
    $migration->down();
    $migration->down();

    expect(Schema::hasColumn('document_categories', 'role'))->toBeFalse();

    $migration->up();
});
