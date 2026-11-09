<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The phones linked to an account (`linked_devices`): a phone that scans documents without a password.
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('linked_devices', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            Columns::required($table, 'user_id', 'users');
            $table->string('name');
            $table->char('token_hash', 64)->unique()->comment('sha256 of the secret in the cookie of the phone');
            $table->string('session_id')->nullable()->comment('The session of the phone, deleted when the phone is forgotten');
            $table->dateTime('last_used_at')->nullable()->comment('The last time the phone entered');
            $table->dateTime('last_upload_at')->nullable()->comment('The last time the phone uploaded a document');
            Columns::system($table);
        });
    }

    /**
     * Drops it.
     */
    public function down(): void
    {
        Schema::dropIfExists('linked_devices');
    }
};
