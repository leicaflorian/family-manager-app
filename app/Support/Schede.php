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
 * I tipi dei collegamenti polimorfici, scritti in un posto solo: la morph map
 * di Eloquent, i tipi che un documento accetta tra i collegamenti e i nomi
 * delle collection di Directus.
 */
final class Schede
{
    /**
     * Il tipo di ogni modello che può stare in un collegamento polimorfico:
     * il nome al singolare della tabella.
     *
     * @var array<string, class-string<Model>>
     */
    public const array TIPI = [
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
     * Le schede a cui si collega un documento (le 15 tabelle ponte di
     * Directus), con la relazione di `Document` che le legge.
     *
     * @var array<string, string>
     */
    public const array CON_DOCUMENTI = [
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
     * Il tipo di un modello, come lo scrive la morph map.
     *
     * @param  class-string<Model>  $classe
     * @return string|null il tipo, o null se il modello non ha un tipo
     */
    public static function tipoDi(string $classe): ?string
    {
        $tipo = array_search($classe, self::TIPI, true);

        return $tipo === false ? null : $tipo;
    }

    /**
     * Il tipo di una collection di Directus: `utilities` diventa `utility`.
     *
     * @param  string  $collection  il nome della tabella di Directus
     * @return string|null il tipo, o null se la collection non ha un tipo
     */
    public static function tipoDaCollection(string $collection): ?string
    {
        foreach (self::TIPI as $tipo => $classe) {
            if ((new $classe)->getTable() === $collection) {
                return $tipo;
            }
        }

        return null;
    }
}
