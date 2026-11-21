<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The link to the bank: connections, transactions read from the bank, the columns that tie an account to a
 * connection, the rule of a planned item, the switch of the family and the links of the notices of the bell.
 * Everything is added, nothing changes: the code of the previous release keeps working.
 */
return new class extends Migration
{
    /**
     * Creates the tables and adds the columns.
     */
    public function up(): void
    {
        Schema::create('bank_connections', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('provider');
            $table->string('institution_name');
            $table->char('institution_country', 2);
            $table->text('session')->nullable()->comment('The session of the bank, encrypted; empty until the consent is given');
            $table->dateTime('valid_until')->nullable();
            $table->string('status')->default('active');
            $table->dateTime('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('expiry_notified_at')->nullable();
            Columns::system($table);
            $table->unique(['family_id', 'provider', 'institution_name']);
        });

        Schema::table('financial_accounts', function (Blueprint $table): void {
            Columns::optional($table, 'bank_connection_id', 'bank_connections');
            $table->string('bank_account_key')->nullable()->comment('Stays the same from one connection to the next');
            $table->string('bank_account_uid')->nullable()->comment('Used in the calls to the bank: changes with every connection');
        });

        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            Columns::optional($table, 'bank_connection_id', 'bank_connections');
            Columns::required($table, 'financial_account_id', 'financial_accounts');
            $table->string('provider_id', 191);
            $table->date('booked_on')->nullable();
            $table->date('value_on')->nullable();
            $table->decimal('amount', 12, 2)->comment('With the sign: negative is an outgoing payment');
            $table->char('currency', 3);
            $table->text('text');
            $table->string('counterparty')->nullable();
            $table->string('status')->default('booked');
            Columns::optional($table, 'expense_id', 'expenses');
            Columns::optional($table, 'income_id', 'incomes');
            $table->string('matched_by')->nullable();
            $table->dateTime('ignored_at')->nullable();
            $table->dateTime('declined_at')->nullable();
            Columns::system($table);
            $table->unique(['financial_account_id', 'provider_id']);
            $table->index(['family_id', 'booked_on']);
        });

        Schema::table('planned_expenses', function (Blueprint $table): void {
            $table->string('bank_match', 200)->nullable()->comment('The text that marks the payments of the bank that settle this item');
        });
        Schema::table('planned_incomes', function (Blueprint $table): void {
            $table->string('bank_match', 200)->nullable()->comment('The text that marks the payments of the bank that settle this item');
        });

        Schema::table('families', function (Blueprint $table): void {
            $table->boolean('bank_feed_enabled')->default(false)->comment('The owner turns the link to the bank on for the family');
        });

        Schema::table('reminder_notices', function (Blueprint $table): void {
            // Cascade, not null: a notice with every link empty would be read as the notice of the mail limit.
            $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('bank_connection_id')->nullable()->constrained('bank_connections')->cascadeOnDelete();
        });
    }

    /**
     * Removes them, in the opposite order.
     */
    public function down(): void
    {
        Schema::table('reminder_notices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_transaction_id');
            $table->dropConstrainedForeignId('bank_connection_id');
        });
        Schema::table('families', function (Blueprint $table): void {
            $table->dropColumn('bank_feed_enabled');
        });
        Schema::table('planned_incomes', function (Blueprint $table): void {
            $table->dropColumn('bank_match');
        });
        Schema::table('planned_expenses', function (Blueprint $table): void {
            $table->dropColumn('bank_match');
        });
        Schema::dropIfExists('bank_transactions');
        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_connection_id');
            $table->dropColumn(['bank_account_key', 'bank_account_uid']);
        });
        Schema::dropIfExists('bank_connections');
    }
};
