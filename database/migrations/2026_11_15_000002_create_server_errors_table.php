<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The server errors of the last 30 days (`server_errors`), one short row each, to attach to a report of a problem.
 * Not a family table: an error can happen before anyone is signed in, so `user_id` and `family_id` may be empty.
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('server_errors', function (Blueprint $table): void {
            $table->id();
            $table->string('request_id', 36)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('family_id')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path', 2048)->nullable();
            $table->string('class');
            $table->string('message', 500);
            $table->string('file', 500)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Drops the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_errors');
    }
};
