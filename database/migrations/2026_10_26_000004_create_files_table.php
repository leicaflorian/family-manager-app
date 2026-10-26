<?php

declare(strict_types=1);

use App\Support\Schema\Colonne;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I file caricati (al posto di `directus_files` di Directus), e la foto delle persone.
 */
return new class extends Migration
{
    /**
     * Crea la tabella e collega `people.photo_id`.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            Colonne::sistema($table);
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->foreign('photo_id')->references('id')->on('files')->nullOnDelete();
        });
    }

    /**
     * Elimina la tabella.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropForeign(['photo_id']);
        });

        Schema::dropIfExists('files');
    }
};
