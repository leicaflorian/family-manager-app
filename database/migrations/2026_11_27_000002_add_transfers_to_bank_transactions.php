<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bank transaction can be a transfer between two accounts of the family: the account on the other side, the other
 * half of the transfer when both accounts are read from the bank, and the proposals the person turned down. Everything
 * is added and empty for the rows already there.
 */
return new class extends Migration
{
    /**
     * Adds the columns.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            Columns::optional($table, 'transfer_account_id', 'financial_accounts');
            Columns::optional($table, 'transfer_pair_id', 'bank_transactions');
            $table->json('rejected_proposals')->nullable()->comment('The proposals the person said no to, so they are not made again');
        });
    }

    /**
     * Removes them.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transfer_pair_id');
            $table->dropConstrainedForeignId('transfer_account_id');
            $table->dropColumn('rejected_proposals');
        });
    }
};
