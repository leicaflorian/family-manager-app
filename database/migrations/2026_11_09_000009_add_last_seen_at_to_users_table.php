<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an account last used the app (`users.last_seen_at`): the owner panel shows it as *Last access*.
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('last_seen_at')->nullable();
        });
    }

    /**
     * Drops the column.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('last_seen_at');
        });
    }
};
