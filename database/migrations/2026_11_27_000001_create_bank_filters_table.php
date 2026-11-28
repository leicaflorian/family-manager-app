<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The filters of the bank: what a filter takes (words, direction, account, amounts or an advanced pattern) and what it
 * does with it, and the column of a bank transaction that remembers the filter that sorted it. Everything is added.
 */
return new class extends Migration
{
    /**
     * Creates the table and adds the column.
     */
    public function up(): void
    {
        Schema::create('bank_filters', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('name');
            $table->json('contains')->comment('Words or phrases the text must all have');
            $table->json('excludes')->comment('Words or phrases the text must not have');
            $table->string('direction', 20)->default('expense')->comment('expense or income');
            // Cascade: without its account a filter would read every account
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->cascadeOnDelete();
            $table->decimal('amount_min', 12, 2)->nullable();
            $table->decimal('amount_max', 12, 2)->nullable();
            $table->string('pattern', 200)->nullable()->comment('The expression of an advanced filter, without delimiters');
            $table->string('fee_mode', 20)->default('none');
            $table->json('fee_contains')->nullable();
            $table->json('fee_excludes')->nullable();
            $table->string('action', 20);
            // Cascade: without its planned item a filter that pays a due date means nothing
            $table->foreignId('planned_expense_id')->nullable()->constrained('planned_expenses')->cascadeOnDelete();
            $table->foreignId('planned_income_id')->nullable()->constrained('planned_incomes')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->boolean('title_with_month')->default(false);
            Columns::optional($table, 'category_id', 'expense_categories');
            Columns::optional($table, 'organization_id', 'organizations');
            Columns::optional($table, 'person_id', 'people');
            $table->string('card_type')->nullable();
            $table->unsignedBigInteger('card_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('transfer_account_id')->nullable()->constrained('financial_accounts')->cascadeOnDelete();
            Columns::system($table);
        });

        Schema::table('bank_transactions', function (Blueprint $table): void {
            Columns::optional($table, 'bank_filter_id', 'bank_filters');
        });
    }

    /**
     * Removes them, in the opposite order.
     */
    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_filter_id');
        });
        Schema::dropIfExists('bank_filters');
    }
};
