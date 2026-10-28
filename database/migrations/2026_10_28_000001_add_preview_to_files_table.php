<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The preview of a file: the JPEG made from a PDF, and whether making it failed.
 */
return new class extends Migration
{
    /**
     * Adds the columns.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->string('preview_path')->nullable()->after('to_sort')
                ->comment('The JPEG of the preview, made the first time it is asked');
            $table->boolean('preview_failed')->default(false)->after('preview_path')
                ->comment('The preview could not be made: it is not tried again');
        });
    }

    /**
     * Drops the columns.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn(['preview_path', 'preview_failed']);
        });
    }
};
