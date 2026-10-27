<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a family was copied from Directus: `directus:copy --replace` makes
 * again only a family that has it, never one that registered itself.
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->timestamp('copied_from_directus_at')->nullable()->after('welcomed_at');
        });
    }

    /**
     * Drops the column.
     */
    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->dropColumn('copied_from_directus_at');
        });
    }
};
