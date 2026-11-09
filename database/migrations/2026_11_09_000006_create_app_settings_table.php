<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The settings of the installation (`app_settings`), written from the admin panel: one row per setting, which wins over the `.env`.
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable()->comment('Plain text, or encrypted for the keys');
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Drops it.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
