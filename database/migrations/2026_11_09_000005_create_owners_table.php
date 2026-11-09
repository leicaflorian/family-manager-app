<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The owner accounts (`owners`): the people who run the app and enter the admin panel. They are not part of any family.
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('app_authentication_secret')->nullable()->comment('Encrypted secret of the authenticator app');
            $table->text('app_authentication_recovery_codes')->nullable()->comment('Encrypted recovery codes of the authenticator app');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Drops it.
     */
    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
