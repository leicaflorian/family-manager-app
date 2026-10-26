<?php

declare(strict_types=1);

use App\Support\Schema\Colonne;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gli inviti a entrare in una famiglia, e la data in cui la famiglia ha finito
 * il *Benvenuto*.
 */
return new class extends Migration
{
    /**
     * Crea la tabella degli inviti e aggiunge `families.welcomed_at`.
     */
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->timestamp('welcomed_at')->nullable()->after('name');
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('email');
            $table->string('role');
            // La persona che c'è già; senza, l'invito dice chi creare.
            Colonne::facoltativo($table, 'person_id', 'people');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('person_role')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            Colonne::sistema($table);
        });
    }

    /**
     * Elimina la tabella e la colonna.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::table('families', function (Blueprint $table): void {
            $table->dropColumn('welcomed_at');
        });
    }
};
