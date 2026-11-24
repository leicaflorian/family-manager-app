<?php

declare(strict_types=1);

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

        $this->fill('vehicles.road_tax_due_date', ['bollo', 'road tax']);
        $this->fill('vehicles.inspection_due_date', ['revision', 'inspection']);
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

    /**
     * Gives a deadline to the planned expenses of vehicles without one whose title holds one of the words.
     *
     * @param  string  $field  the deadline
     * @param  list<string>  $words  the words of the title, in lower case
     */
    private function fill(string $field, array $words): void
    {
        DB::table('planned_expenses')
            ->where('card_type', 'vehicle')
            ->whereNull('deadline_field')
            ->where(function ($query) use ($words): void {
                foreach ($words as $word) {
                    $query->orWhereRaw('LOWER(title) LIKE ?', ['%'.$word.'%']);
                }
            })
            ->update(['deadline_field' => $field]);
    }
};
