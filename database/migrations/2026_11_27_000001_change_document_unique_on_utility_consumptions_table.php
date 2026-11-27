<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One bill can carry two utilities (electricity and gas): the unique key on the document becomes one on the document
 * and the utility. The new key is created first, so the foreign key on `document_id` always has an index.
 */
return new class extends Migration
{
    /**
     * Adds the wider unique key and drops the old one.
     */
    public function up(): void
    {
        Schema::table('utility_consumptions', function (Blueprint $table): void {
            $table->unique(['document_id', 'utility_id'], 'utility_consumptions_document_utility_unique');
        });

        Schema::table('utility_consumptions', function (Blueprint $table): void {
            $table->dropUnique(['document_id']);
        });
    }

    /**
     * Puts the old unique key back, unless two rows share a document.
     *
     * @throws RuntimeException When a document has more than one consumption row.
     */
    public function down(): void
    {
        $shared = DB::table('utility_consumptions')
            ->whereNotNull('document_id')
            ->groupBy('document_id')
            ->havingRaw('count(*) > 1')
            ->value('document_id');

        if ($shared !== null) {
            throw new RuntimeException('Cannot restore the unique key on document_id: some bills have a consumption for more than one utility.');
        }

        Schema::table('utility_consumptions', function (Blueprint $table): void {
            $table->unique('document_id');
        });

        Schema::table('utility_consumptions', function (Blueprint $table): void {
            $table->dropUnique('utility_consumptions_document_utility_unique');
        });
    }
};
