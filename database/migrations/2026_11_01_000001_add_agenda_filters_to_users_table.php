<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The filters of the Agenda (period, person, areas turned off), kept in the account so they follow it to every device.
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('agenda_filters')->nullable()->after('role')
                ->comment('The filters of the Agenda: months, person_id and hidden_areas; null until the person changes them');
        });
    }

    /**
     * Drops the column.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('agenda_filters');
        });
    }
};
