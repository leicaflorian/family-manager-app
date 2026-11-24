<?php

declare(strict_types=1);

use App\Support\VehicleDeadlineFromTitle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The deadline a planned expense pays (`deadline_field`, the key of the kind of deadline, like
 * `vehicles.inspection_due_date`). The planned expenses of vehicles that already exist take it from their title.
 */
return new class extends Migration
{
    /**
     * Adds the column, empty, and fills it for the planned expenses of vehicles whose title says road tax or inspection.
     * Safe to run again: the column is added only once and a deadline already chosen is kept.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('planned_expenses', 'deadline_field')) {
            Schema::table('planned_expenses', function (Blueprint $table): void {
                $table->string('deadline_field', 64)->nullable();
            });
        }

        DB::table('planned_expenses')->where('card_type', 'vehicle')->whereNull('deadline_field')->get(['id', 'title'])
            ->each(static function (object $row): void {
                $field = VehicleDeadlineFromTitle::of($row->title);

                if ($field !== null) {
                    DB::table('planned_expenses')->where('id', $row->id)->update(['deadline_field' => $field]);
                }
            });
    }

    /**
     * Drops the column.
     */
    public function down(): void
    {
        Schema::table('planned_expenses', function (Blueprint $table): void {
            $table->dropColumn('deadline_field');
        });
    }
};
