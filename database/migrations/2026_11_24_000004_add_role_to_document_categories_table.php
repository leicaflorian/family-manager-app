<?php

declare(strict_types=1);

use App\Support\GiveDocumentRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A document category can have a fixed role (`role`), kept when the family renames or moves it, one per family.
 * Every family gets the category *Vehicle registration document* with its role, as {@see GiveDocumentRoles} gives it.
 * Only a nullable column and new rows, so the code of the previous release keeps working.
 */
return new class extends Migration
{
    /**
     * Adds the column with its unique index and gives each family the category.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('document_categories', 'role')) {
            Schema::table('document_categories', function (Blueprint $table): void {
                $table->string('role')->nullable()->after('card_type')->comment('What the app means by the category, once per family');
                $table->unique(['family_id', 'role']);
            });
        }

        foreach (DB::table('families')->orderBy('id')->pluck('id') as $familyId) {
            GiveDocumentRoles::family(is_numeric($familyId) ? (int) $familyId : 0);
        }
    }

    /**
     * Drops the index and the column.
     */
    public function down(): void
    {
        if (Schema::hasColumn('document_categories', 'role')) {
            Schema::table('document_categories', function (Blueprint $table): void {
                // The foreign key of the family may lean on the unique index (MySQL drops its own index when a wider one covers it): give it one back first
                if (! Schema::hasIndex('document_categories', ['family_id'])) {
                    $table->index('family_id');
                }

                $table->dropUnique(['family_id', 'role']);
                $table->dropColumn('role');
            });
        }
    }
};
