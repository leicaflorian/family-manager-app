<?php

declare(strict_types=1);

namespace App\Support\Schema;

use Illuminate\Database\Schema\Blueprint;

/**
 * The columns every table of family data repeats, written once for all the
 * migrations.
 */
final class Columns
{
    /**
     * A required, indexed `family_id`: deleting a family deletes its data too.
     */
    public static function family(Blueprint $table): void
    {
        $table->foreignId('family_id')->constrained()->cascadeOnDelete();
    }

    /**
     * The system fields: who created and who last changed the row (empty once
     * the account is gone), and when.
     */
    public static function system(Blueprint $table): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    }

    /**
     * An optional link to another table: when the linked row is deleted, the
     * link becomes empty (as in Directus).
     *
     * @param  string  $column  for example `organization_id`
     * @param  string  $foreignTable  for example `organizations`
     */
    public static function optional(Blueprint $table, string $column, string $foreignTable): void
    {
        $table->foreignId($column)->nullable()->constrained($foreignTable)->nullOnDelete();
    }

    /**
     * A required link to another table: the row only lives with the one it
     * belongs to (an employment with its person), and goes away with it.
     *
     * @param  string  $column  for example `person_id`
     * @param  string  $foreignTable  for example `people`
     */
    public static function required(Blueprint $table, string $column, string $foreignTable): void
    {
        $table->foreignId($column)->constrained($foreignTable)->cascadeOnDelete();
    }
}
