<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Belonging;
use App\Models\Document;
use App\Models\Employment;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Filing;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\Income;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PlannedExpense;
use App\Models\PlannedIncome;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\Utility;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

/**
 * The types of the polymorphic links, written in one place: Eloquent's morph
 * map, the types a document accepts among its links and the names of the
 * Directus collections.
 */
final class Linkables
{
    /**
     * The type of every model that can sit in a polymorphic link: the
     * singular name of its table.
     *
     * @var array<string, class-string<Model>>
     */
    public const array TYPES = [
        'home' => Home::class,
        'utility' => Utility::class,
        'belonging' => Belonging::class,
        'maintenance_record' => MaintenanceRecord::class,
        'vehicle' => Vehicle::class,
        'insurance_policy' => InsurancePolicy::class,
        'financial_account' => FinancialAccount::class,
        'loan' => Loan::class,
        'filing' => Filing::class,
        'employment' => Employment::class,
        'health_event' => HealthEvent::class,
        'enrollment' => Enrollment::class,
        'subscription' => Subscription::class,
        'expense' => Expense::class,
        'income' => Income::class,
        'document' => Document::class,
        'person' => Person::class,
        'organization' => Organization::class,
        'planned_expense' => PlannedExpense::class,
        'planned_income' => PlannedIncome::class,
        'reminder' => Reminder::class,
    ];

    /**
     * The records a document links to (the 15 junction tables of Directus),
     * with the `Document` relation that reads them.
     *
     * @var array<string, string>
     */
    public const array WITH_DOCUMENTS = [
        'home' => 'homes',
        'utility' => 'utilities',
        'belonging' => 'belongings',
        'maintenance_record' => 'maintenanceRecords',
        'vehicle' => 'vehicles',
        'insurance_policy' => 'insurancePolicies',
        'financial_account' => 'financialAccounts',
        'loan' => 'loans',
        'filing' => 'filings',
        'employment' => 'employments',
        'health_event' => 'healthEvents',
        'enrollment' => 'enrollments',
        'subscription' => 'subscriptions',
        'expense' => 'expenses',
        'income' => 'incomes',
    ];

    /**
     * The type of a model, as the morph map writes it.
     *
     * @param  class-string<Model>  $class
     * @return string|null the type, or null if the model has no type
     */
    public static function typeOf(string $class): ?string
    {
        $type = array_search($class, self::TYPES, true);

        return $type === false ? null : $type;
    }

    /**
     * The type of a Directus collection: `utilities` becomes `utility`.
     *
     * @param  string  $collection  the name of the Directus table
     * @return string|null the type, or null if the collection has no type
     */
    public static function typeOfCollection(string $collection): ?string
    {
        foreach (self::TYPES as $type => $class) {
            if ((new $class)->getTable() === $collection) {
                return $type;
            }
        }

        return null;
    }
}
