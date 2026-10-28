<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The card a file of *Upload* comes from, so its form starts linked to it
 * even when *Upload* is opened again later, from the home page or the menu.
 */
return new class extends Migration
{
    /**
     * Adds the columns.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->string('link_type')->nullable()->after('to_sort')
                ->comment('The type of the card the file was uploaded from, while it waits for its document');
            $table->unsignedBigInteger('link_id')->nullable()->after('link_type')
                ->comment('The id of the card the file was uploaded from, while it waits for its document');
        });
    }

    /**
     * Drops the columns.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn(['link_type', 'link_id']);
        });
    }
};
