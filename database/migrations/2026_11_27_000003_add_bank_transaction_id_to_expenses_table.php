<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bank transaction that paid an expense together with others: one debit of a bill of electricity and gas pays
 * two planned expenses. `bank_transactions.expense_id` keeps pointing at the first one; every expense of the debit has
 * this column. Empty for all the expenses there are, so the version before works with it.
 */
return new class extends Migration
{
    /**
     * Adds the column, empty, with its foreign key and index.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('bank_transaction_id')->nullable()->after('planned_expense_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Drops the column.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_transaction_id');
        });
    }
};
