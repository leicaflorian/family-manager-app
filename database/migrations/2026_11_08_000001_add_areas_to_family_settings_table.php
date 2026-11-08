<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The areas a family ordered and hid: `{"order": [...], "hidden": [...]}` with the area values, null for the default.
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('family_settings', function (Blueprint $table): void {
            $table->json('areas')->nullable()->after('mail_inbox_notify');
        });
    }

    /**
     * Drops the column.
     */
    public function down(): void
    {
        Schema::table('family_settings', function (Blueprint $table): void {
            $table->dropColumn('areas');
        });
    }
};
