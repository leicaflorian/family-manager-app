<?php

declare(strict_types=1);

use App\Support\DefaultCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A document category belongs to a type of card (`card_type`), so the import can propose the card its documents
 * go with. The starter categories of the existing families get theirs, found by the exact name they were created
 * with (English key or Italian translation); a renamed category, or one that already has a card, is left alone.
 */
return new class extends Migration
{
    /**
     * Adds the column, if it is missing, and fills it for the starter categories.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('document_categories', 'card_type')) {
            Schema::table('document_categories', function (Blueprint $table): void {
                $table->string('card_type')->nullable()->after('color')->comment('The type of card the documents of the category go with');
            });
        }

        foreach (DefaultCategories::documentCardTypes() as $name => $type) {
            $names = array_values(array_unique([$name, __($name, [], 'it')]));

            DB::table('document_categories')->whereNull('card_type')->whereIn('name', $names)->update(['card_type' => $type]);
        }
    }

    /**
     * Drops the column.
     */
    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table): void {
            $table->dropColumn('card_type');
        });
    }
};
