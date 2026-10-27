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
 * Every model holding the data of a family goes here: created in family A,
 * it is not found when signed in from family B.
 */
dataset('family models', [
    Person::class, File::class, Organization::class, DocumentCategory::class,
    Home::class, Vehicle::class, FinancialAccount::class, Employment::class,
    Utility::class, Belonging::class, InsurancePolicy::class, Loan::class,
    Filing::class, HealthEvent::class, Enrollment::class, Subscription::class,
    MaintenanceRecord::class, ExpenseCategory::class, PlannedExpense::class,
    PlannedExpenseException::class, Expense::class, PlannedIncome::class,
    PlannedIncomeException::class, Income::class, Document::class,
    ReminderGroup::class, Reminder::class, FamilySetting::class, Invitation::class,
]);

test('a row of family A is not found from family B', function (string $class): void {
    /** @var class-string<Model> $class */
    $familyA = Family::factory()->create();
    $familyB = Family::factory()->create();
    $row = $class::factory()->for($familyA)->create();

    $this->actingAs(User::factory()->for($familyA)->create());
    expect($class::find($row->getKey()))->not->toBeNull();

    $this->actingAs(User::factory()->for($familyB)->create());
    expect($class::all())->toHaveCount(0)
        ->and($class::find($row->getKey()))->toBeNull()
        ->and($class::count())->toBe(0);
})->with('family models');

test('the factories link rows of the same family', function (): void {
    $family = Family::factory()->create();
    $employment = Employment::factory()->for($family)->create();
    $document = Document::factory()->for($family)->create();

    expect($employment->person()->withoutGlobalScopes()->value('family_id'))->toBe($family->id)
        ->and($document->file()->withoutGlobalScopes()->value('family_id'))->toBe($family->id);
});
