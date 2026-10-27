<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The invitations to join a family, and the date the family finished the
 * *Welcome*.
 */
return new class extends Migration
{
    /**
     * Creates the invitations table and adds `families.welcomed_at`.
     */
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table): void {
            $table->timestamp('welcomed_at')->nullable()->after('name');
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('email');
            $table->string('role');
            // The existing person; without one, the invitation says who to create.
            Columns::optional($table, 'person_id', 'people');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('person_role')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            Columns::system($table);
        });
    }

    /**
     * Drops the table and the column.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::table('families', function (Blueprint $table): void {
            $table->dropColumn('welcomed_at');
        });
    }
};
