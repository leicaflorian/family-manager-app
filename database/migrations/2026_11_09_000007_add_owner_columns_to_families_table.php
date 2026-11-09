<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the owner panel needs to know about a family: when it was suspended, when it will be deleted, until when
 * it lets the owners look inside, and its own reading limit.
 */
return new class extends Migration
{
    /**
     * Adds the columns.
     */
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('delete_after')->nullable();
            $table->dateTime('support_until')->nullable();
            $table->unsignedInteger('reading_limit')->nullable()->comment('Readings a month for this family; empty means the limit of everyone');
        });
    }

    /**
     * Drops the columns.
     */
    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->dropColumn(['suspended_at', 'delete_after', 'support_until', 'reading_limit']);
        });
    }
};
