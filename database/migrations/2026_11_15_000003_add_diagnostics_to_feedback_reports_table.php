<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a report of a problem carries besides its text: the screenshot of the page (on the private disk under
 * `feedback/`), the details of the browser and the account's server errors.
 */
return new class extends Migration
{
    /**
     * Adds the columns.
     */
    public function up(): void
    {
        Schema::table('feedback_reports', function (Blueprint $table): void {
            $table->string('screenshot_path')->nullable()->after('file_size')->comment('On the local disk, under feedback/');
            $table->unsignedInteger('screenshot_size')->nullable()->after('screenshot_path')->comment('Bytes');
            $table->json('diagnostics')->nullable()->after('screenshot_size')->comment('Device, console errors, failed requests, last pages');
            $table->json('server_errors')->nullable()->after('diagnostics')->comment('The account\'s server errors around the sending');
        });
    }

    /**
     * Drops them.
     */
    public function down(): void
    {
        Schema::table('feedback_reports', function (Blueprint $table): void {
            $table->dropColumn(['screenshot_path', 'screenshot_size', 'diagnostics', 'server_errors']);
        });
    }
};
