<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The documents and their links to the records. `documentables` takes the
 * place of the 15 junction tables of Directus (`homes_documents`, …).
 */
return new class extends Migration
{
    /**
     * Creates the tables.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('title');
            // A document is its file: without the file there is nothing left to see
            Columns::required($table, 'file_id', 'files');
            Columns::optional($table, 'category_id', 'document_categories');
            $table->string('status')->default('active');
            Columns::optional($table, 'person_id', 'people');
            Columns::optional($table, 'organization_id', 'organizations');
            Columns::optional($table, 'planned_expense_id', 'planned_expenses');
            $table->date('date')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('documentables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->morphs('documentable');
            $table->unique(['document_id', 'documentable_type', 'documentable_id'], 'documentables_unico');
        });
    }

    /**
     * Drops the tables.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentables');
        Schema::dropIfExists('documents');
    }
};
