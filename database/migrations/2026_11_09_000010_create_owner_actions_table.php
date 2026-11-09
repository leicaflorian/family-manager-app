<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The log of what the owner does to a family (`owner_actions`). `family_id` has no constraint and `family_name` is
 * copied: the row stays when the family is deleted.
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('owner_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('family_id')->nullable()->index();
            $table->string('family_name');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Drops the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_actions');
    }
};
