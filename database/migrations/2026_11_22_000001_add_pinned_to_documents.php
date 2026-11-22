<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A document of a person can be pinned at the top of the person's page: when and by whom.
 */
return new class extends Migration
{
    /**
     * Adds the columns and the index of the pinned documents of a person.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->timestamp('pinned_at')->nullable();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['person_id', 'pinned_at']);
        });
    }

    /**
     * Drops them.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['person_id', 'pinned_at']);
            $table->dropConstrainedForeignId('pinned_by');
            $table->dropColumn('pinned_at');
        });
    }
};
