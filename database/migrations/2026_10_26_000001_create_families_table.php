<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le famiglie: ogni dato dell'app appartiene a una di loro.
 */
return new class extends Migration
{
    /**
     * Crea la tabella.
     */
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Elimina la tabella.
     */
    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
