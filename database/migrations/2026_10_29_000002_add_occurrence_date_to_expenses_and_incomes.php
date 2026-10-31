<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The due date of the planned expense or income that a payment settles, so a
 * payment made early or late still pays the right one.
 */
return new class extends Migration
{
    /**
     * Adds the columns and their indexes.
     */
    public function up(): void
    {
        foreach (['expenses' => 'planned_expense_id', 'incomes' => 'planned_income_id'] as $table => $planned) {
            Schema::table($table, function (Blueprint $table) use ($planned): void {
                $table->date('occurrence_date')->nullable()->after($planned)
                    ->comment('The original due date of the planned item that this payment settles');
                $table->index([$planned, 'occurrence_date']);
            });
        }
    }

    /**
     * Drops the indexes and the columns.
     */
    public function down(): void
    {
        foreach (['expenses' => 'planned_expense_id', 'incomes' => 'planned_income_id'] as $table => $planned) {
            Schema::table($table, function (Blueprint $table) use ($planned): void {
                $table->dropIndex([$planned, 'occurrence_date']);
                $table->dropColumn('occurrence_date');
            });
        }
    }
};
