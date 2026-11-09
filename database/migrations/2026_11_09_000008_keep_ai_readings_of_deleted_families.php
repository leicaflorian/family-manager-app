<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The cost of the AI calls outlives the family: deleting a family empties `ai_readings.family_id` instead of
 * deleting the rows.
 */
return new class extends Migration
{
    /**
     * Makes `family_id` optional and nulls it on delete.
     */
    public function up(): void
    {
        Schema::table('ai_readings', function (Blueprint $table): void {
            $table->dropForeign(['family_id']);
        });
        Schema::table('ai_readings', function (Blueprint $table): void {
            $table->foreignId('family_id')->nullable()->change();
            $table->foreign('family_id')->references('id')->on('families')->nullOnDelete();
        });
    }

    /**
     * Puts the cascade back; rows with no family are deleted first, since the column is required again.
     */
    public function down(): void
    {
        DB::table('ai_readings')->whereNull('family_id')->delete();
        Schema::table('ai_readings', function (Blueprint $table): void {
            $table->dropForeign(['family_id']);
        });
        Schema::table('ai_readings', function (Blueprint $table): void {
            $table->foreignId('family_id')->nullable(false)->change();
            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
        });
    }
};
