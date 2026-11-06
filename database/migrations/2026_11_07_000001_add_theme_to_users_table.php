<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The theme every account chose: `system` (follows the device), `light` or `dark`.
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('theme')->default('system')->comment('system, light or dark');
        });
    }

    /**
     * Drops the column.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('theme');
        });
    }
};
