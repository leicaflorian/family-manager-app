<?php

declare(strict_types=1);

use App\Support\Schema\Colonne;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le impostazioni di una famiglia: una riga per famiglia. Il nome sta in
 * `families.name`; chiavi dell'AI, cron e modelli sono dell'installazione e
 * non stanno qui.
 */
return new class extends Migration
{
    /**
     * Crea la tabella.
     */
    public function up(): void
    {
        Schema::create('family_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mail_inbox_recipient')->nullable();
            $table->json('mail_inbox_notify')->nullable();
            Colonne::sistema($table);
        });
    }

    /**
     * Elimina la tabella.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_settings');
    }
};
