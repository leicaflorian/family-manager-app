<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reports that people send to the owner from *Report a problem or an idea* (`feedback_reports`). Who sent it is
 * `created_by`; the file, when there is one, is on the private disk under `feedback/`, apart from the family's files.
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('feedback_reports', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('kind')->comment('problem or idea');
            $table->text('message');
            $table->string('page', 2048)->comment('The path of the page the person was on');
            $table->string('user_agent', 512)->nullable();
            $table->string('layout')->nullable()->comment('phone, tablet or computer');
            $table->string('file_path')->nullable()->comment('On the local disk, under feedback/');
            $table->string('file_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->unsignedInteger('file_size')->nullable()->comment('Bytes');
            Columns::system($table);
        });
    }

    /**
     * Drops it.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_reports');
    }
};
