<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per AI call (`ai_readings`), to know what the readings cost. Rows are never changed.
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('ai_readings', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            Columns::optional($table, 'user_id', 'users');
            Columns::optional($table, 'file_id', 'files');
            $table->string('purpose');
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['family_id', 'created_at']);
        });
    }

    /**
     * Drops the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_readings');
    }
};
