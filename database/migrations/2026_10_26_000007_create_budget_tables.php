<?php

declare(strict_types=1);

use App\Support\Schema\Colonne;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Il bilancio: categorie delle spese, spese e entrate previste con le loro
 * eccezioni, spese e entrate (cartella `budget` di Directus). In fondo collega
 * `expense_id` delle visite e delle manutenzioni.
 */
return new class extends Migration
{
    /**
     * Crea le tabelle.
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('planned_expenses', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title');
            $table->decimal('amount', 10, 2);
            Colonne::facoltativo($table, 'category_id', 'expense_categories');
            $table->boolean('is_recurring')->default(true);
            $table->integer('interval')->default(1);
            $table->string('interval_unit')->default('month');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_estimated')->default(false);
            $table->boolean('is_auto_created')->default(false);
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'financial_account_id', 'financial_accounts');
            Colonne::facoltativo($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'utility_id', 'utilities');
            Colonne::facoltativo($table, 'insurance_policy_id', 'insurance_policies');
            Colonne::facoltativo($table, 'loan_id', 'loans');
            Colonne::facoltativo($table, 'enrollment_id', 'enrollments');
            Colonne::facoltativo($table, 'home_id', 'homes');
            Colonne::facoltativo($table, 'vehicle_id', 'vehicles');
            Colonne::facoltativo($table, 'subscription_id', 'subscriptions');
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('planned_expense_exceptions', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            Colonne::obbligatorio($table, 'planned_expense_id', 'planned_expenses');
            $table->string('type')->default('skip');
            $table->date('occurrence_date')->nullable();
            $table->decimal('amount_override', 10, 2)->nullable();
            $table->date('date_override')->nullable();
            $table->integer('sort')->nullable();
            $table->string('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->date('date')->nullable();
            Colonne::facoltativo($table, 'planned_expense_id', 'planned_expenses');
            Colonne::facoltativo($table, 'category_id', 'expense_categories');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'financial_account_id', 'financial_accounts');
            Colonne::facoltativo($table, 'person_id', 'people');
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('planned_incomes', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('type');
            $table->boolean('is_recurring')->default(true);
            $table->integer('interval')->nullable();
            $table->string('interval_unit')->default('month');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_estimated')->default(false);
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'financial_account_id', 'financial_accounts');
            Colonne::facoltativo($table, 'employment_id', 'employments');
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('planned_income_exceptions', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            Colonne::obbligatorio($table, 'planned_income_id', 'planned_incomes');
            $table->string('type')->default('skip');
            $table->date('occurrence_date');
            $table->decimal('amount_override', 10, 2)->nullable();
            $table->date('date_override')->nullable();
            $table->integer('sort')->nullable();
            $table->string('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('incomes', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->date('date');
            $table->string('type');
            Colonne::facoltativo($table, 'planned_income_id', 'planned_incomes');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'financial_account_id', 'financial_accounts');
            Colonne::facoltativo($table, 'employment_id', 'employments');
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        foreach (['health_events', 'maintenance_records'] as $tabella) {
            Schema::table($tabella, function (Blueprint $table): void {
                $table->foreign('expense_id')->references('id')->on('expenses')->nullOnDelete();
            });
        }
    }

    /**
     * Elimina le tabelle, al contrario.
     */
    public function down(): void
    {
        foreach (['health_events', 'maintenance_records'] as $tabella) {
            Schema::table($tabella, function (Blueprint $table): void {
                $table->dropForeign(['expense_id']);
            });
        }

        foreach (['incomes', 'planned_income_exceptions', 'planned_incomes', 'expenses', 'planned_expense_exceptions', 'planned_expenses', 'expense_categories'] as $tabella) {
            Schema::dropIfExists($tabella);
        }
    }
};
