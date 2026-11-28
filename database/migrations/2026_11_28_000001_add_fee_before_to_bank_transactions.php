<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A commission that joined an expense whose amount already had it in (recorded by hand as the bank charged it) leaves
 * the amount as it was and only sets the fee; this column says so and keeps the fee the expense had before, so taking
 * the commission back gives the expense what it had. Empty for every row already there and for a commission that was
 * added to the amount.
 */
return new class extends Migration
{
    /**
     * Adds the column.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->decimal('fee_before', 12, 2)->nullable()->comment('Of a commission that was already in the amount of its expense: the fee the expense had before it joined');
        });
    }

    /**
     * Removes it.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropColumn('fee_before');
        });
    }
};
