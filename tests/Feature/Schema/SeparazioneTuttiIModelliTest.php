<?php

declare(strict_types=1);

use App\Models\Belonging;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Family;
use App\Models\FamilySetting;
use App\Models\File;
use App\Models\Filing;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\Income;
use App\Models\InsurancePolicy;
use App\Models\Invitation;
use App\Models\Loan;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedExpenseException;
use App\Models\PlannedIncome;
use App\Models\PlannedIncomeException;
use App\Models\Reminder;
use App\Models\ReminderGroup;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/*
 * Ogni modello con i dati di una famiglia entra qui: creato nella famiglia A,
 * non si trova entrando dalla famiglia B.
 */
dataset('modelli della famiglia', [
    Person::class, File::class, Organization::class, DocumentCategory::class,
    Home::class, Vehicle::class, FinancialAccount::class, Employment::class,
    Utility::class, Belonging::class, InsurancePolicy::class, Loan::class,
    Filing::class, HealthEvent::class, Enrollment::class, Subscription::class,
    MaintenanceRecord::class, ExpenseCategory::class, PlannedExpense::class,
    PlannedExpenseException::class, Expense::class, PlannedIncome::class,
    PlannedIncomeException::class, Income::class, Document::class,
    ReminderGroup::class, Reminder::class, FamilySetting::class, Invitation::class,
]);

test('un dato della famiglia A non si trova dalla famiglia B', function (string $classe): void {
    /** @var class-string<Model> $classe */
    $famigliaA = Family::factory()->create();
    $famigliaB = Family::factory()->create();
    $dato = $classe::factory()->for($famigliaA)->create();

    $this->actingAs(User::factory()->for($famigliaA)->create());
    expect($classe::find($dato->getKey()))->not->toBeNull();

    $this->actingAs(User::factory()->for($famigliaB)->create());
    expect($classe::all())->toHaveCount(0)
        ->and($classe::find($dato->getKey()))->toBeNull()
        ->and($classe::count())->toBe(0);
})->with('modelli della famiglia');

test('le fabbriche collegano dati della stessa famiglia', function (): void {
    $famiglia = Family::factory()->create();
    $lavoro = Employment::factory()->for($famiglia)->create();
    $documento = Document::factory()->for($famiglia)->create();

    expect($lavoro->person()->withoutGlobalScopes()->value('family_id'))->toBe($famiglia->id)
        ->and($documento->file()->withoutGlobalScopes()->value('family_id'))->toBe($famiglia->id);
});
