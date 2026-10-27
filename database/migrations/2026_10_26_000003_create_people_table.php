<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people of the family, with or without an account.
 */
return new class extends Migration
{
    /**
     * Creates the table. `photo_id` is linked to `files` in the files migration.
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('initials');
            $table->string('role');
            $table->date('birth_date')->nullable();
            $table->string('tax_code')->nullable();
            $table->unsignedBigInteger('photo_id')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            Columns::system($table);
        });
    }

    /**
     * Drops the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
