<?php

declare(strict_types=1);

use App\Models\Family;
use App\Models\Home;
use App\Models\Utility;
use App\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The migration is run by hand, down and up again, instead of `migrate:rollback`: the tests share one batch, so
 * a rollback by step would undo whichever migration is last. MySQL commits the transaction of RefreshDatabase at
 * the first change of a table, and Laravel then migrates the database again for the next test.
 */
beforeEach(function (): void {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_10_29_000003_link_expenses_to_cards.php');
    $migration->down();

    $family = Family::factory()->create();
    $this->panda = Vehicle::factory()->create(['family_id' => $family->id, 'name' => 'Panda']);
    $this->home = Home::factory()->create(['family_id' => $family->id]);
    $utility = Utility::factory()->create(['family_id' => $family->id, 'home_id' => $this->home->id]);

    $planned = ['family_id' => $family->id, 'amount' => 30, 'interval_unit' => 'month'];
    $this->insurance = DB::table('planned_expenses')->insertGetId([...$planned, 'title' => 'RC auto Panda', 'vehicle_id' => $this->panda->id]);
    $this->rent = DB::table('planned_expenses')->insertGetId([...$planned, 'title' => 'Affitto', 'utility_id' => $utility->id, 'home_id' => $this->home->id]);

    $migration->up();
});

it('moves the vehicle of a planned expense into the card pair', function (): void {
    $row = DB::table('planned_expenses')->find($this->insurance);

    expect($row->card_type)->toBe('vehicle')
        ->and($row->card_id)->toBe($this->panda->id);
});

it('keeps the first card when a planned expense has two', function (): void {
    $row = DB::table('planned_expenses')->find($this->rent);

    expect($row->card_type)->toBe('home')
        ->and($row->card_id)->toBe($this->home->id);
});

it('drops the seven columns', function (): void {
    expect(Schema::hasColumn('planned_expenses', 'vehicle_id'))->toBeFalse()
        ->and(Schema::hasColumns('planned_expenses', ['card_type', 'card_id']))->toBeTrue()
        ->and(Schema::hasColumns('expenses', ['card_type', 'card_id']))->toBeTrue()
        ->and(Schema::hasColumn('expense_categories', 'card_type'))->toBeTrue();
});
