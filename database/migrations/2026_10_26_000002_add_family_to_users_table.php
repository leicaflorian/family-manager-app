<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every account belongs to a family, with its level.
 */
return new class extends Migration
{
    /**
     * Adds family and level.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('family_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->after('family_id')->default(Role::Member->value);
        });
    }

    /**
     * Removes family and level.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('family_id');
            $table->dropColumn('role');
        });
    }
};
