<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reminders, their alert groups and what they refer to.
 * `remindables` takes the place of `reminders_related_to` in Directus.
 */
return new class extends Migration
{
    /**
     * Creates the tables.
     */
    public function up(): void
    {
        Schema::create('reminder_groups', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->json('deadline_types')->nullable();
            $table->json('notifications')->nullable();
            Columns::system($table);
        });

        Schema::create('reminders', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('title');
            $table->date('due_date')->index();
            $table->string('status')->default('open');
            Columns::optional($table, 'person_id', 'people');
            $table->boolean('is_automatic')->default(false);
            $table->string('source_field')->nullable();
            Columns::optional($table, 'reminder_group_id', 'reminder_groups');
            $table->json('notifications_sent')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('remindables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->morphs('remindable');
            $table->unique(['reminder_id', 'remindable_type', 'remindable_id'], 'remindables_unico');
        });
    }

    /**
     * Drops the tables.
     */
    public function down(): void
    {
        Schema::dropIfExists('remindables');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('reminder_groups');
    }
};
