<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The settings of a family: one row per family. The name sits in
 * `families.name`; AI keys, cron jobs and models belong to the installation and
 * are not here.
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('family_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mail_inbox_recipient')->nullable();
            $table->json('mail_inbox_notify')->nullable();
            Columns::system($table);
        });
    }

    /**
     * Drops the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_settings');
    }
};
