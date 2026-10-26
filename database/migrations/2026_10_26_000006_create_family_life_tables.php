<?php

declare(strict_types=1);

use App\Support\Schema\Colonne;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le schede della vita della famiglia (cartella `family_life` di Directus).
 * `health_events.expense_id` e `maintenance_records.expense_id` si collegano a
 * `expenses` nella migrazione del bilancio, che viene dopo.
 */
return new class extends Migration
{
    /**
     * Crea le tabelle, prima quelle a cui le altre puntano.
     */
    public function up(): void
    {
        Schema::create('homes', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_owned')->default(false);
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('plate_number')->nullable();
            $table->integer('registration_year')->nullable();
            $table->boolean('is_owned')->default(true);
            Colonne::facoltativo($table, 'person_id', 'people');
            $table->date('road_tax_due_date')->nullable();
            $table->date('inspection_due_date')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('active');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'person_id', 'people');
            $table->string('last_four_digits', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('employments', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            Colonne::obbligatorio($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            $table->string('job_title')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('utilities', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('type');
            $table->string('status')->default('active');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'home_id', 'homes');
            Colonne::facoltativo($table, 'person_id', 'people');
            $table->string('customer_code')->nullable();
            $table->string('supply_point_code')->nullable();
            $table->date('activation_date')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('belongings', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->string('status')->default('in_use');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            Colonne::facoltativo($table, 'home_id', 'homes');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('insurance_policies', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title')->nullable();
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('policy_number')->nullable();
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'vehicle_id', 'vehicles');
            Colonne::facoltativo($table, 'employment_id', 'employments');
            Colonne::facoltativo($table, 'home_id', 'homes');
            $table->date('start_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->string('status')->default('active');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'financial_account_id', 'financial_accounts');
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->integer('installments_count')->nullable();
            $table->decimal('installment_amount', 10, 2)->nullable();
            $table->decimal('final_installment_amount', 10, 2)->nullable();
            $table->decimal('total_to_pay', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->integer('installments_paid')->nullable();
            $table->integer('installments_left')->nullable();
            $table->decimal('residual_amount', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('filings', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title');
            $table->string('type');
            $table->string('status')->default('draft');
            $table->integer('reference_year')->nullable();
            Colonne::facoltativo($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            $table->date('submission_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('health_events', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title');
            $table->string('type');
            Colonne::obbligatorio($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            $table->date('date');
            $table->date('next_checkup_date')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->text('outcome')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            Colonne::obbligatorio($table, 'person_id', 'people');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('school_year')->nullable();
            $table->string('class_name')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('active');
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            $table->string('account')->nullable();
            Colonne::facoltativo($table, 'financial_account_id', 'financial_accounts');
            $table->date('activation_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->boolean('is_auto_renew')->default(true);
            $table->date('trial_end_date')->nullable();
            $table->string('manage_url')->nullable();
            $table->text('notes')->nullable();
            Colonne::facoltativo($table, 'person_id', 'people');
            Colonne::sistema($table);
        });

        Schema::create('maintenance_records', function (Blueprint $table): void {
            $table->id();
            Colonne::famiglia($table);
            $table->string('title');
            $table->date('date');
            $table->string('type')->nullable();
            Colonne::facoltativo($table, 'organization_id', 'organizations');
            Colonne::facoltativo($table, 'home_id', 'homes');
            Colonne::facoltativo($table, 'belonging_id', 'belongings');
            Colonne::facoltativo($table, 'vehicle_id', 'vehicles');
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->text('notes')->nullable();
            Colonne::sistema($table);
        });
    }

    /**
     * Elimina le tabelle, al contrario.
     */
    public function down(): void
    {
        foreach (['maintenance_records', 'subscriptions', 'enrollments', 'health_events', 'filings', 'loans', 'insurance_policies', 'belongings', 'utilities', 'employments', 'financial_accounts', 'vehicles', 'homes'] as $tabella) {
            Schema::dropIfExists($tabella);
        }
    }
};
