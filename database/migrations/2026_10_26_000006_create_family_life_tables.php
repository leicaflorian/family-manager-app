<?php

declare(strict_types=1);

use App\Support\Schema\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The records of the family's life (folder `family_life` in Directus).
 * `health_events.expense_id` and `maintenance_records.expense_id` are linked to
 * `expenses` in the budget migration, which comes later.
 */
return new class extends Migration
{
    /**
     * Creates the tables, first those the others point to.
     */
    public function up(): void
    {
        Schema::create('homes', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_owned')->default(false);
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('plate_number')->nullable();
            $table->integer('registration_year')->nullable();
            $table->boolean('is_owned')->default(true);
            Columns::optional($table, 'person_id', 'people');
            $table->date('road_tax_due_date')->nullable();
            $table->date('inspection_due_date')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('active');
            Columns::optional($table, 'organization_id', 'organizations');
            Columns::optional($table, 'person_id', 'people');
            $table->string('last_four_digits', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('employments', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            Columns::required($table, 'person_id', 'people');
            Columns::optional($table, 'organization_id', 'organizations');
            $table->string('job_title')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('utilities', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('type');
            $table->string('status')->default('active');
            Columns::optional($table, 'organization_id', 'organizations');
            Columns::optional($table, 'home_id', 'homes');
            Columns::optional($table, 'person_id', 'people');
            $table->string('customer_code')->nullable();
            $table->string('supply_point_code')->nullable();
            $table->date('activation_date')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('belongings', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('name');
            $table->string('status')->default('in_use');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            Columns::optional($table, 'home_id', 'homes');
            Columns::optional($table, 'organization_id', 'organizations');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('insurance_policies', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('title')->nullable();
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('policy_number')->nullable();
            Columns::optional($table, 'organization_id', 'organizations');
            Columns::optional($table, 'person_id', 'people');
            Columns::optional($table, 'vehicle_id', 'vehicles');
            Columns::optional($table, 'employment_id', 'employments');
            Columns::optional($table, 'home_id', 'homes');
            $table->date('start_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('name');
            $table->string('status')->default('active');
            Columns::optional($table, 'organization_id', 'organizations');
            Columns::optional($table, 'person_id', 'people');
            Columns::optional($table, 'financial_account_id', 'financial_accounts');
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
            Columns::system($table);
        });

        Schema::create('filings', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('title');
            $table->string('type');
            $table->string('status')->default('draft');
            $table->integer('reference_year')->nullable();
            Columns::optional($table, 'person_id', 'people');
            Columns::optional($table, 'organization_id', 'organizations');
            $table->date('submission_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('health_events', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('title');
            $table->string('type');
            Columns::required($table, 'person_id', 'people');
            Columns::optional($table, 'organization_id', 'organizations');
            $table->date('date');
            $table->date('next_checkup_date')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->text('outcome')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            Columns::required($table, 'person_id', 'people');
            Columns::optional($table, 'organization_id', 'organizations');
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('school_year')->nullable();
            $table->string('class_name')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('active');
            Columns::optional($table, 'organization_id', 'organizations');
            $table->string('account')->nullable();
            Columns::optional($table, 'financial_account_id', 'financial_accounts');
            $table->date('activation_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->boolean('is_auto_renew')->default(true);
            $table->date('trial_end_date')->nullable();
            $table->string('manage_url')->nullable();
            $table->text('notes')->nullable();
            Columns::optional($table, 'person_id', 'people');
            Columns::system($table);
        });

        Schema::create('maintenance_records', function (Blueprint $table): void {
            $table->id();
            Columns::family($table);
            $table->string('title');
            $table->date('date');
            $table->string('type')->nullable();
            Columns::optional($table, 'organization_id', 'organizations');
            Columns::optional($table, 'home_id', 'homes');
            Columns::optional($table, 'belonging_id', 'belongings');
            Columns::optional($table, 'vehicle_id', 'vehicles');
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->text('notes')->nullable();
            Columns::system($table);
        });
    }

    /**
     * Drops the tables, in reverse order.
     */
    public function down(): void
    {
        foreach (['maintenance_records', 'subscriptions', 'enrollments', 'health_events', 'filings', 'loans', 'insurance_policies', 'belongings', 'utilities', 'employments', 'financial_accounts', 'vehicles', 'homes'] as $tabella) {
            Schema::dropIfExists($tabella);
        }
    }
};
