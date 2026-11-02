<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The day a reminder was put off to with *Postpone*. It stays apart from `due_date`, the day of the deadline:
 * the sync of an automatic reminder matches it to its card by `due_date`, and would undo a moved one.
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->date('postponed_to')->nullable()->after('due_date')
                ->comment('The day the reminder was put off to; null when it is the day of the deadline');
        });
    }

    /**
     * Drops the column.
     */
    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->dropColumn('postponed_to');
        });
    }
};
