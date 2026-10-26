<?php

declare(strict_types=1);

use App\Support\Schema\Colonne;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le persone della famiglia, con o senza un account.
 */
return new class extends Migration
{
    /**
     * Crea la tabella. `photo_id` si collega a `files` nella migrazione dei file.
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('initials');
            $table->string('role');
            $table->date('birth_date')->nullable();
            $table->string('tax_code')->nullable();
            $table->unsignedBigInteger('photo_id')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });
    }

    /**
     * Elimina la tabella.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
