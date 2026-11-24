<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two features a family can have switched on: the Budget and the mail for the bills, with the pause of the Budget
 * (the first day it was turned off, the day it was last turned on again).
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
            // The first day the Budget was turned off and the day it was last turned on again: the due dates that fell
            // between them, unpaid, are neither late nor to pay
            $table->date('budget_paused_on')->nullable();
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
            $table->dropColumn(['budget_enabled', 'mail_enabled', 'budget_paused_on', 'budget_started_on']);
        });
    }
};
