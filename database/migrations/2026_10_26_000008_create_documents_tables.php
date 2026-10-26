<?php

declare(strict_types=1);

use App\Support\Schema\Colonne;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I documenti e i loro collegamenti alle schede. `documentables` prende il
 * posto delle 15 tabelle ponte di Directus (`homes_documents`, …).
 */
return new class extends Migration
{
    /**
     * Crea le tabelle.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title');
            // Un documento è il suo file: senza il file non resta niente da vedere
            Colonne::obbligatorio($table, 'file_id', 'files');
            Colonne::facoltativo($table, 'category_id', 'document_categories');
            $table->string('status')->default('active');
            Colonne::facoltativo($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'planned_expense_id', 'planned_expenses');
            $table->date('date')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('documentables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->morphs('documentable');
            $table->unique(['document_id', 'documentable_type', 'documentable_id'], 'documentables_unico');
        });
    }

    /**
     * Elimina le tabelle.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentables');
        Schema::dropIfExists('documents');
    }
};
