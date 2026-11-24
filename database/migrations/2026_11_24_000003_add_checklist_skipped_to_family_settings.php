<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The steps of *To begin* a family took away with *Not needed*, and the ones it has done, each as a list of keys.
 */
return new class extends Migration
{
    /**
     * Adds the columns, empty: no step is taken away, and the done ones are written the first time the Home finds them done.
     */
    public function up(): void
    {
        Schema::table('family_settings', function (Blueprint $table): void {
            $table->json('checklist_skipped')->nullable();
            $table->json('checklist_done')->nullable();
        });
    }

    /**
     * Drops them.
     */
    public function down(): void
    {
        Schema::table('family_settings', function (Blueprint $table): void {
            $table->dropColumn(['checklist_skipped', 'checklist_done']);
        });
    }
};
