<?php

declare(strict_types=1);

use App\Support\Schema\Colonne;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I promemoria, i loro gruppi di avvisi e a cosa si riferiscono.
 * `remindables` prende il posto di `reminders_related_to` di Directus.
 */
return new class extends Migration
{
    /**
     * Crea le tabelle.
     */
    public function up(): void
    {
        Schema::create('reminder_groups', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->json('deadline_types')->nullable();
            $table->json('notifications')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('reminders', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title');
            $table->date('due_date')->index();
            $table->string('status')->default('open');
            Colonne::facoltativo($table, 'person_id', 'people');
            $table->boolean('is_automatic')->default(false);
            $table->string('source_field')->nullable();
            Colonne::facoltativo($table, 'reminder_group_id', 'reminder_groups');
            $table->json('notifications_sent')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('remindables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->morphs('remindable');
            $table->unique(['reminder_id', 'remindable_type', 'remindable_id'], 'remindables_unico');
        });
    }

    /**
     * Elimina le tabelle.
     */
    public function down(): void
    {
        Schema::dropIfExists('remindables');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('reminder_groups');
    }
};
