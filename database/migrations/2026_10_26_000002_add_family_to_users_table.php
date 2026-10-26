<?php

declare(strict_types=1);

use App\Enums\Ruolo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ogni account appartiene a una famiglia, con il suo livello.
 */
return new class extends Migration
{
    /**
     * Aggiunge famiglia e livello.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('family_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->after('family_id')->default(Ruolo::Member->value);
        });
    }

    /**
     * Toglie famiglia e livello.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('family_id');
            $table->dropColumn('role');
        });
    }
};
