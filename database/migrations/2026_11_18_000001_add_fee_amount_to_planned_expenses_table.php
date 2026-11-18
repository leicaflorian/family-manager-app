<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The commission of a planned expense: added to its amount on every due date and copied on every payment.
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('planned_expenses', function (Blueprint $table): void {
            $table->decimal('fee_amount', 10, 2)->nullable()->after('amount')->comment('Added to the amount of every due date');
        });
    }

    /**
     * Drops it.
     */
    public function down(): void
    {
        Schema::table('planned_expenses', function (Blueprint $table): void {
            $table->dropColumn('fee_amount');
        });
    }
};
