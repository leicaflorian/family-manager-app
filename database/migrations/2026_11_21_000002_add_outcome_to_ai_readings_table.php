<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What came of a reading of the consumption of a bill (`consumption` readings only).
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('ai_readings', function (Blueprint $table): void {
            $table->string('outcome', 24)->nullable()->after('purpose');
        });
    }

    /**
     * Drops it.
     */
    public function down(): void
    {
        Schema::table('ai_readings', function (Blueprint $table): void {
            $table->dropColumn('outcome');
        });
    }
};
