<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The planned expenses a document waits on beyond the one in `documents.planned_expense_id`: a bill that carries
 * electricity and gas waits on both. Like `documentables` it has no `family_id`: the two rows it links have it.
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('document_planned_expense', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('planned_expense_id')->constrained()->cascadeOnDelete();
            $table->unique(['document_id', 'planned_expense_id']);
        });
    }

    /**
     * Drops the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_planned_expense');
    }
};
