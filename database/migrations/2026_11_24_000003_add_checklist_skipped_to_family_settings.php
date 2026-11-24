<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The steps of *To begin* a family took away with *Not needed*, as a list of their keys.
 */
return new class extends Migration
{
    /**
     * Adds the column, empty: no step is taken away.
     */
    public function up(): void
    {
        Schema::table('family_settings', function (Blueprint $table): void {
            $table->json('checklist_skipped')->nullable();
        });
    }

    /**
     * Drops it.
     */
    public function down(): void
    {
        Schema::table('family_settings', function (Blueprint $table): void {
            $table->dropColumn('checklist_skipped');
        });
    }
};
