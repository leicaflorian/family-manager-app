<?php

declare(strict_types=1);

use App\Support\Schema\Colonne;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enti e categorie dei documenti (cartella `registry` e `archive` di Directus).
 */
return new class extends Migration
{
    /**
     * Crea le tabelle.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->string('icon')->nullable();
            $table->json('types')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->foreignId('logo_id')->nullable()->constrained('files')->nullOnDelete();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('document_categories', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort')->nullable();
            Colonne::sistema($table);
        });
    }

    /**
     * Elimina le tabelle.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('organizations');
    }
};
