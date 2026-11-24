<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two features a family can have switched on: the Budget and the mail for the bills, with the day the Budget was
 * last turned on.
 */
return new class extends Migration
{
    /**
     * Adds the switches off by default (so the previous code keeps working), then turns them on for the families
     * that exist: they already have both.
     */
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->boolean('budget_enabled')->default(false);
            $table->boolean('mail_enabled')->default(false);
            // The day the Budget was last turned on: the due dates that fell before it, while it was off, are not late
            $table->date('budget_started_on')->nullable();
        });

        DB::table('families')->update(['budget_enabled' => true, 'mail_enabled' => true]);
    }

    /**
     * Drops them.
     */
    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->dropColumn(['budget_enabled', 'mail_enabled', 'budget_started_on']);
        });
    }
};
