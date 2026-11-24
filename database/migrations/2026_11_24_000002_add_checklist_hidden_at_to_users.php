<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an account pressed *Hide* on *To begin*, the checklist of the Home.
 */
return new class extends Migration
{
    /**
     * Adds the column, empty: every account keeps seeing the list until it hides it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('checklist_hidden_at')->nullable();
        });
    }

    /**
     * Drops it.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('checklist_hidden_at');
        });
    }
};
