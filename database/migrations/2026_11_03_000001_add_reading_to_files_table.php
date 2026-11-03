<?php

declare(strict_types=1);

use App\Enums\FileSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The state of the AI reading of a file, where the file came from, a note and a checksum to find copies.
 */
return new class extends Migration
{
    /**
     * Adds the columns and the index.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->string('reading_status')->nullable()
                ->comment('queued, reading, done, failed, skipped or off; null until the file is queued');
            $table->json('reading')->nullable()->comment('What the AI read from the file');
            $table->string('reading_error')->nullable()->comment('Why the reading failed');
            $table->string('source')->default(FileSource::Upload->value)
                ->comment('upload, mail, phone or scanner');
            $table->text('note')->nullable();
            $table->char('checksum', 64)->nullable()->comment('SHA-256 of the content, to find copies');
            $table->index(['family_id', 'checksum']);
        });
    }

    /**
     * Drops the index and the columns.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropIndex(['family_id', 'checksum']);
            $table->dropColumn(['reading_status', 'reading', 'reading_error', 'source', 'note', 'checksum']);
        });
    }
};
