<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The answer of the AI about a received mail (`received_mails.screening`), kept as soon as it is paid: a try of the
 * job after it uses the answer again instead of asking (and paying) twice.
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('received_mails', function (Blueprint $table): void {
            $table->json('screening')->nullable()->after('organization_id');
        });
    }

    /**
     * Removes the column.
     */
    public function down(): void
    {
        Schema::table('received_mails', function (Blueprint $table): void {
            $table->dropColumn('screening');
        });
    }
};
