<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a bill of electricity, gas or water says about the consumption of a period (`utility_consumptions`).
 */
return new class extends Migration
{
    /**
     * Creates the table.
     */
    public function up(): void
    {
        Schema::create('utility_consumptions', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            Columns::required($table, 'utility_id', 'utilities');
            Columns::optional($table, 'document_id', 'documents');
            $table->unique('document_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('quantity', 12, 3)->comment('In the unit of the utility: kWh, Smc or m3');
            $table->string('unit', 8);
            $table->decimal('amount', 10, 2)->nullable()->comment('The total of the bill');
            $table->decimal('energy_amount', 10, 2)->nullable()->comment('The energy part of the bill');
            $table->decimal('quantity_f1', 12, 3)->nullable();
            $table->decimal('quantity_f2', 12, 3)->nullable();
            $table->decimal('quantity_f3', 12, 3)->nullable();
            $table->boolean('is_estimated')->default(false);
            $table->boolean('has_adjustments')->default(false);
            $table->string('source', 16)->comment('reading or manual');
            Columns::system($table);
            $table->index(['utility_id', 'period_end']);
        });
    }

    /**
     * Drops the table.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_consumptions');
    }
};
