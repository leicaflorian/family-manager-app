<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The drawings gallery: the category shown as a gallery, the favourite documents and the pages of a file.
 */
return new class extends Migration
{
    /**
     * Adds the three columns, all empty or with a default so the previous code keeps working.
     */
    public function up(): void
    {
        Schema::table('document_categories', function (Blueprint $table): void {
            $table->boolean('gallery')->default(false);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('is_favorite')->default(false);
        });

        Schema::table('files', function (Blueprint $table): void {
            $table->unsignedSmallInteger('page_count')->nullable();
        });
    }

    /**
     * Drops them.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn('page_count');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('is_favorite');
        });

        Schema::table('document_categories', function (Blueprint $table): void {
            $table->dropColumn('gallery');
        });
    }
};
