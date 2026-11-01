<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notices of the bell (`reminder_notices`) and the language of the account (`users.locale`),
 * which the morning round uses to write the email.
 */
return new class extends Migration
{
    /**
     * Creates the table and the column.
     */
    public function up(): void
    {
        Schema::create('reminder_notices', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            Columns::required($table, 'reminder_id', 'reminders');
            Columns::required($table, 'user_id', 'users');
            $table->date('due_date');
            $table->integer('offset');
            $table->dateTime('sent_at');
            $table->dateTime('read_at')->nullable();
            Columns::system($table);
            $table->index(['user_id', 'read_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->char('locale', 2)->nullable()->after('role')
                ->comment('The language of the account, saved from the browser; null until the first request');
        });
    }

    /**
     * Drops them.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
        Schema::dropIfExists('reminder_notices');
    }
};
