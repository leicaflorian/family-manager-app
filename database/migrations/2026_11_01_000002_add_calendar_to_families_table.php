<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The family's calendar to subscribe to: its secret, encrypted to show it again and as a fingerprint to find it,
 * and the language of its texts.
 */
return new class extends Migration
{
    /**
     * Adds the columns.
     */
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->text('calendar_token')->nullable()->after('welcomed_at')
                ->comment('The secret of the calendar address, encrypted; null while the calendar is off');
            $table->char('calendar_token_hash', 64)->nullable()->unique()->after('calendar_token')
                ->comment('The sha256 of the secret, to find the family from the address');
            $table->char('calendar_locale', 2)->nullable()->after('calendar_token_hash')
                ->comment('The language of the texts of the calendar, en or it: the one of the page it was turned on in');
        });
    }

    /**
     * Drops the columns.
     */
    public function down(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->dropUnique(['calendar_token_hash']);
            $table->dropColumn(['calendar_token', 'calendar_token_hash', 'calendar_locale']);
        });
    }
};
