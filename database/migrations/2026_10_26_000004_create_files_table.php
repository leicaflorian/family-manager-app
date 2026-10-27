<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The uploaded files (instead of `directus_files` in Directus), and the photo of the people.
 */
return new class extends Migration
{
    /**
     * Creates the table and links `people.photo_id`.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            // Uploaded and waiting for its document: the files of *Upload* still to sort.
            $table->boolean('to_sort')->default(false);
            Columns::system($table);
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->foreign('photo_id')->references('id')->on('files')->nullOnDelete();
        });
    }

    /**
     * Drops the table.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropForeign(['photo_id']);
        });

        Schema::dropIfExists('files');
    }
};
