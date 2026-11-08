<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The files a received mail saved (`received_mails.saved_files`: the key and the name of each), kept on the mail:
 * once a file is deleted its row is gone, and *Received mail* still says it was saved and is no longer there
 * («File eliminato», «non più presente»).
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('received_mails', function (Blueprint $table): void {
            $table->json('saved_files')->nullable()->after('skipped');
        });
    }

    /**
     * Removes the column.
     */
    public function down(): void
    {
        Schema::table('received_mails', function (Blueprint $table): void {
            $table->dropColumn('saved_files');
        });
    }
};
