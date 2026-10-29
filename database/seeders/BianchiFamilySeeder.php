<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Families\CreateFamily;
use App\Enums\DocumentStatus;
use App\Enums\EnrollmentType;
use App\Enums\ExceptionType;
use App\Enums\FilingType;
use App\Enums\FinancialAccountType;
use App\Enums\HealthEventType;
use App\Enums\IncomeType;
use App\Enums\InsurancePolicyType;
use App\Enums\IntervalUnit;
use App\Enums\MaintenanceType;
use App\Enums\OrganizationType;
use App\Enums\PersonRole;
use App\Enums\Role;
use App\Enums\SubscriptionType;
use App\Enums\UtilityType;
use App\Models\Belonging;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\File;
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
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\FileStore;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;

/**
 * The Bianchi family of the canvas: people, accounts, organizations, records,
 * documents with fake files, and the budget (planned expenses and incomes with
 * their payments, and the movements of October 2026).
 *
 * The Bianchis are an Italian family, so their data is in Italian and the family
 * is registered in Italian. The accounts have the test password `password`,
 * written in `README.md`. If Marco's account is already there the seeder stops,
 * so running it twice does not duplicate anything.
 */
final class BianchiFamilySeeder extends Seeder
{
    use Localizable;

    /** The test password of the Bianchi accounts. */
    private const string PASSWORD = 'password';

    /** The language of the family: its categories are created in Italian. */
    private const string LOCALE = 'it';

    /** The organizations: name => type. */
    private const array ORGANIZATIONS = [
        'Edison' => OrganizationType::UtilityProvider,
        'Enel Energia' => OrganizationType::UtilityProvider,
        'Hera' => OrganizationType::UtilityProvider,
        'Fastweb' => OrganizationType::UtilityProvider,
        'BPER Banca' => OrganizationType::Bank,
        'Findomestic' => OrganizationType::Bank,
        'UnipolSai' => OrganizationType::Insurance,
        'Studio Rinaldi' => OrganizationType::Employer,
        'Comune di Bologna' => OrganizationType::PublicOffice,
        'CAF CGIL Bologna' => OrganizationType::Other,
        'Scuola Passo a Due' => OrganizationType::School,
        'Studio Ferri' => OrganizationType::Healthcare,
        'Dott.ssa Neri' => OrganizationType::Healthcare,
        'Netflix' => OrganizationType::Shop,
        'Termoidraulica Rossi' => OrganizationType::Other,
        // The shops of the movements of October 2026.
        'Tper' => OrganizationType::Other,
        'OVS' => OrganizationType::Shop,
        'Coop' => OrganizationType::Shop,
        'Esselunga' => OrganizationType::Shop,
        'Eni' => OrganizationType::Shop,
        'Cinema Odeon' => OrganizationType::Other,
        'Farmacia Comunale' => OrganizationType::Healthcare,
    ];

    /**
     * The icon (a Lucide name) and colour (the area, from the Branding) of the main expense categories, by name.
     * The subcategories take the colour of their main category.
     */
    private const array CATEGORY_LOOKS = [
        'Casa e affitto' => ['house', '#F76B15'],
        'Bollette e utenze' => ['zap', '#F76B15'],
        'Auto' => ['car', '#0797B9'],
        'Polizze e assicurazioni' => ['shield', '#8E4EC6'],
        'Prestiti e finanziamenti' => ['landmark', '#3E63DD'],
        'Lavoro' => ['briefcase', '#6E9F18'],
        'Salute' => ['heart-pulse', '#C13FB8'],
        'Scuola e attività' => ['graduation-cap', '#E5A50A'],
        'Spesa' => ['shopping-cart', '#E35169'],
        'Svago' => ['clapperboard', '#E35169'],
        'Donazioni' => ['hand-heart', '#E35169'],
        'Risparmi' => ['piggy-bank', '#E35169'],
        'Abbigliamento' => ['shirt', '#E35169'],
    ];

    /** The months in Italian, for the titles of the payments. */
    private const array MONTHS = [
        1 => 'gennaio', 2 => 'febbraio', 3 => 'marzo', 4 => 'aprile', 5 => 'maggio', 6 => 'giugno',
        7 => 'luglio', 8 => 'agosto', 9 => 'settembre', 10 => 'ottobre', 11 => 'novembre', 12 => 'dicembre',
    ];

    /** The icons of the subcategories. */
    private const array SUBCATEGORY_ICONS = ['Luce' => 'zap', 'Gas' => 'flame', 'Acqua' => 'droplet', 'Sport' => 'dumbbell'];

    /** @var array<string, Organization> the organizations created, by name */
    private array $organizations = [];

    /** @var array<string, Person> the people, by first name */
    private array $people = [];

    /** @var array<string, Model&object{id: int}> the records, with the name read on the canvas */
    private array $records = [];

    /** @var array<string, PlannedExpense> the planned expenses, by title */
    private array $plannedExpenses = [];

    /** @var array<string, PlannedIncome> the planned incomes, by title */
    private array $plannedIncomes = [];

    /**
     * @param  CreateFamily  $createFamily  the registration action
     * @param  FileStore  $files  the store the fake files go to
     */
    public function __construct(
        private readonly CreateFamily $createFamily,
        private readonly FileStore $files,
    ) {}

    /**
     * Creates the Bianchi family, if it is not there yet.
     */
    public function run(): void
    {
        if (User::query()->where('email', 'marco.bianchi@example.com')->exists()) {
            return;
        }

        $this->withLocale(self::LOCALE, fn () => DB::transaction(function (): void {
            $marco = $this->createFamily->handle('Bianchi', 'Marco', 'Bianchi', 'marco.bianchi@example.com', self::PASSWORD);

            CurrentFamily::using($marco->family_id, function () use ($marco): void {
                $this->createPeople($marco);
                $this->createOrganizations();
                $this->createRecords();
                $this->createDocuments();
                $this->createBudget();
            });

            // The Bianchis have already done the Welcome: they go straight to the Home.
            $marco->family->update(['welcomed_at' => now()]);
        }));
    }

    /**
     * Marco is already there with the registration: Laura is added, with her account, and Giulia, without.
     *
     * @param  User  $marco  Marco's account
     */
    private function createPeople(User $marco): void
    {
        $this->people['Marco'] = Person::query()->where('user_id', $marco->id)->sole();

        $laura = User::query()->create([
            'family_id' => $marco->family_id,
            'name' => 'Laura Bianchi',
            'email' => 'laura.bianchi@example.com',
            'password' => self::PASSWORD,
            'role' => Role::Admin,
        ]);
        // The test accounts sign in straight away, without verifying the email.
        User::query()->whereKey([$marco->id, $laura->id])->update(['email_verified_at' => now()]);

        $this->people['Laura'] = Person::query()->create([
            'first_name' => 'Laura', 'last_name' => 'Bianchi', 'initials' => 'LB',
            'role' => PersonRole::Parent, 'user_id' => $laura->id, 'birth_date' => '1986-04-14',
        ]);
        $this->people['Giulia'] = Person::query()->create([
            'first_name' => 'Giulia', 'last_name' => 'Bianchi', 'initials' => 'GB',
            'role' => PersonRole::Child, 'birth_date' => '2016-05-24',
        ]);
    }

    /**
     * The 22 organizations: the 15 of the canvas and the shops of the movements of October 2026.
     */
    private function createOrganizations(): void
    {
        foreach (self::ORGANIZATIONS as $name => $type) {
            $this->organizations[$name] = Organization::query()->create(['name' => $name, 'types' => [$type]]);
        }
    }

    /**
     * The records that the documents and the pages of the canvas name.
     */
    private function createRecords(): void
    {
        $home = Home::query()->create(['name' => 'Casa di via Roma', 'address' => 'Via Roma 12', 'city' => 'Bologna', 'country' => 'Italia', 'is_owned' => true]);
        $account = FinancialAccount::query()->create(['name' => 'Conto BPER', 'type' => FinancialAccountType::CheckingAccount, 'organization_id' => $this->organizations['BPER Banca']->id, 'person_id' => $this->people['Marco']->id, 'last_four_digits' => '4821', 'is_default' => true]);
        $lauraCard = FinancialAccount::query()->create(['name' => 'Carta Laura', 'type' => FinancialAccountType::Card, 'organization_id' => $this->organizations['BPER Banca']->id, 'person_id' => $this->people['Laura']->id, 'last_four_digits' => '0937']);
        $panda = Vehicle::query()->create(['name' => 'Fiat Panda', 'brand' => 'Fiat', 'model' => 'Panda', 'plate_number' => 'GH 482 TK', 'registration_year' => 2019, 'is_owned' => true, 'person_id' => $this->people['Marco']->id]);

        $this->records = [
            'Casa di via Roma' => $home,
            'Luce - Edison' => $this->utility(UtilityType::Electricity, 'Edison', $home),
            'Gas - Enel' => $this->utility(UtilityType::Gas, 'Enel Energia', $home),
            'Acqua - Hera' => $this->utility(UtilityType::Water, 'Hera', $home),
            'Internet - Fastweb' => $this->utility(UtilityType::Internet, 'Fastweb', $home),
            'Fiat Panda' => $panda,
            'RC auto Panda - UnipolSai' => InsurancePolicy::query()->create(['title' => 'RC auto Panda', 'type' => InsurancePolicyType::Car, 'organization_id' => $this->organizations['UnipolSai']->id, 'person_id' => $this->people['Marco']->id, 'vehicle_id' => $panda->id, 'start_date' => '2025-11-20', 'expiry_date' => '2026-11-20']),
            'Conto BPER' => $account,
            'Mutuo casa' => Loan::query()->create(['name' => 'Mutuo casa', 'organization_id' => $this->organizations['BPER Banca']->id, 'person_id' => $this->people['Marco']->id, 'financial_account_id' => $account->id, 'start_date' => '2018-06-01']),
            // The loan works out its end, totals and instalments paid by itself, and creates its planned expense.
            'Finanziamento Panda' => Loan::query()->create(['name' => 'Finanziamento Panda', 'organization_id' => $this->organizations['Findomestic']->id, 'person_id' => $this->people['Marco']->id, 'financial_account_id' => $account->id, 'total_amount' => 8000, 'installments_count' => 48, 'installment_amount' => 189, 'start_date' => '2024-03-10']),
            'Marco - Studio Rinaldi' => Employment::query()->create(['person_id' => $this->people['Marco']->id, 'organization_id' => $this->organizations['Studio Rinaldi']->id, 'job_title' => 'Geometra']),
            'Laura - Comune di Bologna' => Employment::query()->create(['person_id' => $this->people['Laura']->id, 'organization_id' => $this->organizations['Comune di Bologna']->id, 'job_title' => 'Impiegata']),
            'Netflix' => Subscription::query()->create(['name' => 'Netflix', 'type' => SubscriptionType::Streaming, 'organization_id' => $this->organizations['Netflix']->id, 'person_id' => $this->people['Laura']->id, 'financial_account_id' => $lauraCard->id, 'account' => 'laura.bianchi@email.it', 'activation_date' => '2026-10-05', 'trial_end_date' => '2026-11-04', 'manage_url' => 'https://netflix.com/account', 'is_auto_renew' => true]),
            'Danza Giulia' => Enrollment::query()->create(['person_id' => $this->people['Giulia']->id, 'organization_id' => $this->organizations['Scuola Passo a Due']->id, 'type' => EnrollmentType::Sport, 'school_year' => '2026/27', 'class_name' => 'Danza']),
            'Visite di Giulia' => HealthEvent::query()->create(['title' => 'Visite di Giulia', 'type' => HealthEventType::Visit, 'person_id' => $this->people['Giulia']->id, 'organization_id' => $this->organizations['Dott.ssa Neri']->id, 'date' => '2025-12-11']),
            'Dentista di Laura' => HealthEvent::query()->create(['title' => 'Dentista di Laura', 'type' => HealthEventType::Visit, 'person_id' => $this->people['Laura']->id, 'organization_id' => $this->organizations['Studio Ferri']->id, 'date' => '2026-10-08', 'next_checkup_date' => '2027-04-08', 'outcome' => 'Pulizia, nessuna carie']),
            'Revisione caldaia' => MaintenanceRecord::query()->create(['title' => 'Revisione caldaia', 'type' => MaintenanceType::BoilerService, 'date' => '2026-09-15', 'home_id' => $home->id, 'organization_id' => $this->organizations['Termoidraulica Rossi']->id, 'notes' => 'Controllo annuale e pulizia del bruciatore']),
            'Carta Laura' => $lauraCard,
            'TV Samsung' => Belonging::query()->create(['name' => 'TV Samsung', 'brand' => 'Samsung', 'home_id' => $home->id, 'purchase_date' => '2024-12-20', 'warranty_expiry_date' => '2026-12-20']),
            'ISEE 2027' => Filing::query()->create(['title' => 'ISEE 2027', 'type' => FilingType::Isee, 'reference_year' => 2027, 'organization_id' => $this->organizations['CAF CGIL Bologna']->id]),
        ];
    }

    /**
     * A utility of the home, with its provider.
     *
     * @param  UtilityType  $type  electricity, gas, water or internet
     * @param  string  $provider  the name of the provider
     * @param  Home  $home  the home served
     */
    private function utility(UtilityType $type, string $provider, Home $home): Utility
    {
        return Utility::query()->create(['type' => $type, 'organization_id' => $this->organizations[$provider]->id, 'home_id' => $home->id]);
    }

    /**
     * The 18 documents of the canvas, each with its fake file.
     */
    private function createDocuments(): void
    {
        // title, category (main › sub), person, organization, date, expiry, records, file
        $documents = [
            ['Bolletta luce di ottobre', 'Casa e utenze › Luce', null, 'Edison', '2026-10-26', null, ['Luce - Edison'], 'bolletta-luce-ottobre.pdf'],
            ['Bolletta luce di settembre', 'Casa e utenze › Luce', null, 'Edison', '2026-10-08', null, ['Luce - Edison'], 'bolletta-luce-settembre.pdf'],
            ['Contratto Edison', 'Casa e utenze › Luce', null, 'Edison', '2019-03-01', null, ['Luce - Edison'], 'contratto-edison.pdf'],
            ['Bolletta gas di settembre', 'Casa e utenze › Gas', null, 'Enel Energia', '2026-10-25', null, ['Gas - Enel'], 'bolletta-gas-settembre.pdf'],
            ['Fattura Fastweb', 'Casa e utenze › Internet', null, 'Fastweb', '2026-10-25', null, ['Internet - Fastweb'], 'fattura-fastweb.pdf'],
            ['Busta paga di ottobre', 'Lavoro › Buste paga', 'Laura', 'Comune di Bologna', '2026-10-23', null, ['Laura - Comune di Bologna'], 'busta-paga-laura-ottobre.pdf'],
            ['Busta paga di settembre', 'Lavoro › Buste paga', 'Laura', 'Comune di Bologna', '2026-09-23', null, ['Laura - Comune di Bologna'], 'busta-paga-laura-settembre.pdf'],
            ['Busta paga di agosto', 'Lavoro › Buste paga', 'Laura', 'Comune di Bologna', '2026-08-23', null, ['Laura - Comune di Bologna'], 'busta-paga-laura-agosto.pdf'],
            ['Certificazione Unica 2026', 'Lavoro › CU', 'Laura', 'Comune di Bologna', '2026-03-20', null, ['Laura - Comune di Bologna'], 'cu-2026-laura.pdf'],
            ['Busta paga di settembre', 'Lavoro › Buste paga', 'Marco', 'Studio Rinaldi', '2026-09-27', null, ['Marco - Studio Rinaldi'], 'busta-paga-marco-settembre.pdf'],
            ['Iscrizione danza 2026/27', 'Scuola e attività', 'Giulia', 'Scuola Passo a Due', '2026-09-13', null, ['Danza Giulia'], 'iscrizione-danza.pdf'],
            ['Certificato medico sportivo', 'Salute', 'Giulia', 'Dott.ssa Neri', '2025-12-11', '2026-12-11', ['Visite di Giulia'], 'certificato-medico-sportivo.pdf'],
            ["Carta d'identità", 'Anagrafica e pratiche', 'Giulia', 'Comune di Bologna', '2017-05-24', '2027-05-24', [], 'carta-identita.png'],
            ['Tessera sanitaria', 'Anagrafica e pratiche', 'Giulia', null, '2020-10-20', '2026-10-20', [], 'tessera-sanitaria.pdf'],
            ['Polizza RC auto 2026', 'Assicurazioni e fondi', 'Marco', 'UnipolSai', '2025-11-20', '2026-11-20', ['RC auto Panda - UnipolSai', 'Fiat Panda'], 'polizza-rc-auto.pdf'],
            ['Estratto conto di settembre', 'Banche e finanziamenti', null, 'BPER Banca', '2026-10-05', null, ['Conto BPER'], 'estratto-conto-settembre.pdf'],
            ['Contratto Findomestic', 'Banche e finanziamenti', 'Marco', 'Findomestic', '2024-03-01', null, ['Finanziamento Panda'], 'contratto-findomestic.pdf'],
            ['Scontrino TV Samsung', 'Acquisti, garanzie e abbonamenti › Garanzie e scontrini', null, null, '2024-12-20', '2026-12-20', ['TV Samsung'], 'scontrino-tv.png'],
        ];

        foreach ($documents as [$title, $category, $person, $organization, $date, $expiry, $records, $file]) {
            $document = Document::query()->create([
                'title' => $title,
                'file_id' => $this->file($file)->id,
                'category_id' => $this->category($category)->id,
                'status' => DocumentStatus::Active,
                'person_id' => $person === null ? null : $this->people[$person]->id,
                'organization_id' => $organization === null ? null : $this->organizations[$organization]->id,
                'date' => $date,
                'expiry_date' => $expiry,
            ]);

            foreach ($records as $record) {
                $document->morphedByMany($this->records[$record]::class, 'documentable')->attach($this->records[$record]);
            }
        }
    }

    /**
     * The budget of the canvas: the categories, the planned expenses and incomes with their exceptions, the payments
     * that settle their due dates and the other movements of October 2026.
     *
     * The numbers agree with each other: October 2026 has 1.720,00 € in and 1.284,20 € out in 15 payments, 1.469,30 €
     * planned, and the electricity paid in 2026 is 642,10 € in 9 bills. Every recurring item has the payments of the
     * 12 months before the canvas day, so the only due dates still open are the three of the canvas.
     */
    private function createBudget(): void
    {
        $this->createExpenseCategories();
        $this->createPlannedExpenses();
        $this->createPlannedIncomes();
        // The September bill is the document to pay of the 23/10 due date: 08/10 is inside its window.
        Document::query()->where('title', 'Bolletta luce di settembre')->where('category_id', $this->category('Casa e utenze › Luce')->id)
            ->update(['planned_expense_id' => $this->plannedExpenses['Luce']->id]);
        $this->createPayments();
        $this->createMovements();
    }

    /**
     * The expense categories of the canvas: the Italian names of the board, an icon and the colour of their area.
     */
    private function createExpenseCategories(): void
    {
        // The board calls the school category by its area, and Sport is its subcategory.
        $school = $this->categoryIn(ExpenseCategory::query()->orderBy('sort'), 'Scuola e asilo');
        $school->update(['name' => 'Scuola e attività']);
        $school->children()->create(['name' => 'Sport', 'sort' => 1]);
        ExpenseCategory::query()->create(['name' => 'Abbigliamento', 'sort' => ExpenseCategory::query()->whereNull('parent_id')->count() + 1]);

        foreach (self::CATEGORY_LOOKS as $name => [$icon, $color]) {
            $main = $this->categoryIn(ExpenseCategory::query()->orderBy('sort'), $name);
            $main->update(['icon' => $icon, 'color' => $color]);

            foreach ($main->children as $child) {
                $child->update(['icon' => self::SUBCATEGORY_ICONS[$child->name] ?? $icon, 'color' => $color]);
            }
        }
    }

    /**
     * The 12 planned expenses of *Spese fisse*, each linked to its card, with the exceptions of the canvas.
     *
     * Three numbers of the boards are corrected: the dance fee skips July and August of 2026 and of 2027 (so it is
     * 465,00 € in a year, not 450,00 €), the road tax of the Panda is a planned expense of the vehicle, and the October
     * electricity bill is moved to 06/11 with a different date.
     */
    private function createPlannedExpenses(): void
    {
        // The loan made the planned expense of its instalment by itself, without a category.
        $panda = PlannedExpense::query()->whereMorphedTo('card', $this->records['Finanziamento Panda'])->sole();
        $panda->update(['category_id' => $this->categoryId('Prestiti e finanziamenti')]);
        $this->plannedExpenses['Finanziamento Panda'] = $panda;

        $this->plan('Gas', 55, '2026-10-30', [
            'category_id' => $this->categoryId('Bollette e utenze › Gas'), 'card_type' => 'utility', 'card_id' => $this->records['Gas - Enel']->id,
            'organization_id' => $this->organizations['Enel Energia']->id, 'is_estimated' => true,
        ], [[ExceptionType::AmountOverride, '2026-10-30', 84.20, null, 'Bolletta gas di settembre']]);
        $this->plan('Acqua', 38, '2026-10-31', [
            'category_id' => $this->categoryId('Bollette e utenze › Acqua'), 'card_type' => 'utility', 'card_id' => $this->records['Acqua - Hera']->id,
            'organization_id' => $this->organizations['Hera']->id, 'is_estimated' => true, 'interval' => 2,
        ], [[ExceptionType::AmountOverride, '2026-10-31', 37.90, null, 'Bolletta acqua']]);
        $this->plan('Spesa', 320, '2026-10-01', ['category_id' => $this->categoryId('Spesa'), 'is_estimated' => true, 'person_id' => null]);
        $this->plan('Carburante', 80, '2026-10-01', ['category_id' => $this->categoryId('Auto'), 'card_type' => 'vehicle', 'card_id' => $this->records['Fiat Panda']->id, 'is_estimated' => true]);
        $this->plan('Internet - Fastweb', 29.90, '2026-10-03', [
            'category_id' => $this->categoryId('Bollette e utenze'), 'card_type' => 'utility', 'card_id' => $this->records['Internet - Fastweb']->id,
            'organization_id' => $this->organizations['Fastweb']->id,
        ]);
        $this->plan('Netflix', 13.99, '2026-11-04', [
            'category_id' => $this->categoryId('Svago'), 'card_type' => 'subscription', 'card_id' => $this->records['Netflix']->id,
            'organization_id' => $this->organizations['Netflix']->id, 'financial_account_id' => $this->records['Carta Laura']->id,
            'person_id' => $this->people['Laura']->id,
        ]);
        // The 23rd pays the bill of the month before. The one of October (due 23/11) arrives early, with its amount.
        $this->plan('Luce', 72, '2026-01-23', [
            'category_id' => $this->categoryId('Bollette e utenze › Luce'), 'card_type' => 'utility', 'card_id' => $this->records['Luce - Edison']->id,
            'organization_id' => $this->organizations['Edison']->id, 'is_estimated' => true,
        ], [
            [ExceptionType::AmountOverride, '2026-10-23', 71.30, null, 'Bolletta luce di settembre'],
            [ExceptionType::DateOverride, '2026-11-23', null, '2026-11-06', null],
            [ExceptionType::AmountOverride, '2026-11-23', 62.40, null, 'Bolletta luce di ottobre'],
        ]);
        $this->plan('Mutuo casa', 612, '2018-06-15', [
            'category_id' => $this->categoryId('Casa e affitto'), 'card_type' => 'loan', 'card_id' => $this->records['Mutuo casa']->id,
            'organization_id' => $this->organizations['BPER Banca']->id, 'end_date' => '2041-06-15',
        ]);
        $this->plan('Assicurazione auto', 418, '2025-11-21', [
            'category_id' => $this->categoryId('Polizze e assicurazioni'), 'card_type' => 'insurance_policy', 'card_id' => $this->records['RC auto Panda - UnipolSai']->id,
            'organization_id' => $this->organizations['UnipolSai']->id, 'interval_unit' => IntervalUnit::Year,
        ]);
        $this->plan('Retta danza', 45, '2025-09-05', [
            'category_id' => $this->categoryId('Scuola e attività › Sport'), 'card_type' => 'enrollment', 'card_id' => $this->records['Danza Giulia']->id,
            'organization_id' => $this->organizations['Scuola Passo a Due']->id, 'financial_account_id' => $this->records['Carta Laura']->id,
            'person_id' => $this->people['Giulia']->id,
        ], [
            [ExceptionType::Skip, '2026-07-05', null, null, 'Vacanze'],
            [ExceptionType::Skip, '2026-08-05', null, null, 'Vacanze'],
            [ExceptionType::AmountOverride, '2026-12-05', 60.00, null, 'Comprende la quota del saggio di Natale'],
            [ExceptionType::Skip, '2027-07-05', null, null, 'Vacanze'],
            [ExceptionType::Skip, '2027-08-05', null, null, 'Vacanze'],
        ]);
        $this->plan('Bollo auto', 198, '2026-01-31', [
            'category_id' => $this->categoryId('Auto'), 'card_type' => 'vehicle', 'card_id' => $this->records['Fiat Panda']->id, 'interval_unit' => IntervalUnit::Year,
        ]);
    }

    /**
     * Creates a planned expense, monthly unless the columns say otherwise, on the Conto BPER and Marco by default.
     *
     * @param  string  $title  the title
     * @param  float  $amount  the amount of a due date
     * @param  string  $start  the first due date, `yyyy-mm-dd`
     * @param  array<string, mixed>  $columns  the other columns, which win over the defaults
     * @param  list<array{0: ExceptionType, 1: string, 2: ?float, 3: ?string, 4: ?string}>  $exceptions  type, original date, amount, new date and note of each exception
     */
    private function plan(string $title, float $amount, string $start, array $columns, array $exceptions = []): PlannedExpense
    {
        $plan = PlannedExpense::query()->create([
            'title' => $title, 'amount' => $amount, 'start_date' => $start,
            'financial_account_id' => $this->records['Conto BPER']->id, 'person_id' => $this->people['Marco']->id,
            ...$columns,
        ]);

        foreach ($exceptions as [$type, $occurrence, $overrideAmount, $overrideDate, $notes]) {
            $plan->exceptions()->create(['type' => $type, 'occurrence_date' => $occurrence, 'amount_override' => $overrideAmount, 'date_override' => $overrideDate, 'notes' => $notes]);
        }

        return $this->plannedExpenses[$title] = $plan;
    }

    /**
     * The 2 planned incomes of *Spese fisse*: the salaries of Marco (net, the 27th) and of Laura (net, the 23rd).
     */
    private function createPlannedIncomes(): void
    {
        foreach ([
            ['Stipendio di Marco', 1850, '2026-01-27', 'Marco', 'Marco - Studio Rinaldi', 'Studio Rinaldi'],
            ['Stipendio di Laura', 1720, '2026-01-23', 'Laura', 'Laura - Comune di Bologna', 'Comune di Bologna'],
        ] as [$title, $amount, $start, $person, $employment, $organization]) {
            $this->plannedIncomes[$person] = PlannedIncome::query()->create([
                'title' => $title, 'amount' => $amount, 'type' => IncomeType::Salary, 'start_date' => $start,
                'person_id' => $this->people[$person]->id, 'employment_id' => $this->records[$employment]->id,
                'organization_id' => $this->organizations[$organization]->id, 'financial_account_id' => $this->records['Conto BPER']->id,
            ]);
        }
    }

    /**
     * The expenses and incomes that settle the due dates of the planned ones, up to the canvas day.
     */
    private function createPayments(): void
    {
        $plans = $this->plannedExpenses;
        $luce = [96.50, 91.20, 84.30, 72.80, 64.60, 40.80, 48.80, 74.20, 68.90];

        // The electricity of 2026: 9 bills, 642,10 €. The bill of July was paid a day early.
        foreach ($luce as $index => $amount) {
            $month = CarbonImmutable::parse('2026-01-23')->addMonthsNoOverflow($index);
            $paid = $month->month === 8 ? $month->subDay() : $month;
            $this->pay($plans['Luce'], 'Bolletta luce di '.self::MONTHS[$month->subMonthNoOverflow()->month], $paid->toDateString(), $amount, $month->toDateString());
        }

        // Every instalment of the Panda since 10/03/2024: 32 by the canvas day.
        for ($number = 1; $number <= 32; $number++) {
            $day = CarbonImmutable::parse('2024-03-10')->addMonthsNoOverflow($number - 1)->toDateString();
            $this->pay($plans['Finanziamento Panda'], "Rata {$number} Finanziamento Panda", $day, 189, $day);
        }

        for ($month = CarbonImmutable::parse('2025-11-15'); $month->lessThanOrEqualTo('2026-10-15'); $month = $month->addMonthNoOverflow()) {
            $this->pay($plans['Mutuo casa'], 'Rata mutuo', $month->toDateString(), 612, $month->toDateString());
        }

        // The dance fee: the school year before and the start of this one, without July and August. November was paid early.
        for ($month = CarbonImmutable::parse('2025-09-05'); $month->lessThanOrEqualTo('2026-10-05'); $month = $month->addMonthNoOverflow()) {
            if (! in_array($month->month, [7, 8], true)) {
                $this->pay($plans['Retta danza'], 'Retta di '.self::MONTHS[$month->month], $month->toDateString(), 45, $month->toDateString());
            }
        }

        $this->pay($plans['Retta danza'], 'Retta di novembre', '2026-10-20', 45, '2026-11-05');
        $this->pay($plans['Assicurazione auto'], 'Assicurazione auto', '2025-11-21', 418, '2025-11-21');
        $this->pay($plans['Bollo auto'], 'Bollo auto', '2026-01-31', 198, '2026-01-31');
        $this->pay($plans['Internet - Fastweb'], 'Internet - Fastweb', '2026-10-03', 29.90, '2026-10-03');
        $this->pay($plans['Spesa'], 'Spesa', '2026-10-04', 62.35, '2026-10-01', ['organization_id' => $this->organizations['Esselunga']->id]);
        $this->pay($plans['Carburante'], 'Carburante', '2026-10-12', 30, '2026-10-01', ['organization_id' => $this->organizations['Eni']->id]);

        // The salaries: Marco's 9 of 2026 (Marco's October one has not arrived yet) and Laura's 10.
        foreach ([['Marco', 'Busta paga di', 27, 9], ['Laura', 'Stipendio di', 23, 10]] as [$person, $title, $day, $count]) {
            for ($index = 0; $index < $count; $index++) {
                $month = CarbonImmutable::parse(sprintf('2026-%02d-%02d', $index + 1, $day));
                $this->receive($this->plannedIncomes[$person], "{$title} ".self::MONTHS[$month->month], $month->toDateString());
            }
        }
    }

    /**
     * The movements of October 2026 that no planned expense explains.
     */
    private function createMovements(): void
    {
        // title, date, amount, category, organization, account
        $movements = [
            ['Parcheggio', '2026-10-24', 12.00, 'Auto', 'Tper', 'Conto BPER'],
            ['Abbigliamento', '2026-10-21', 46.80, 'Abbigliamento', 'OVS', 'Conto BPER'],
            ['Spesa', '2026-10-18', 39.15, 'Spesa', 'Coop', 'Conto BPER'],
            ['Cinema', '2026-10-17', 16.00, 'Svago', 'Cinema Odeon', 'Conto BPER'],
            ['Spesa', '2026-10-11', 48.60, 'Spesa', 'Esselunga', 'Conto BPER'],
            // The visit of 90,00 € is two payments, so that October has the 15 payments of the canvas.
            ['Visita dal dentista', '2026-10-08', 80.00, 'Salute', 'Studio Ferri', 'Carta Laura'],
            ['Radiografia', '2026-10-08', 10.00, 'Salute', 'Studio Ferri', 'Carta Laura'],
            ['Farmaci', '2026-10-07', 18.40, 'Salute', 'Farmacia Comunale', 'Conto BPER'],
        ];

        foreach ($movements as [$title, $date, $amount, $category, $organization, $account]) {
            Expense::query()->create([
                'title' => $title, 'date' => $date, 'amount' => $amount, 'category_id' => $this->categoryId($category),
                'organization_id' => $this->organizations[$organization]->id, 'financial_account_id' => $this->records[$account]->id,
            ]);
        }
    }

    /**
     * An expense that settles one due date of a planned expense, taking the rest from the planned expense.
     *
     * @param  PlannedExpense  $plan  the planned expense paid
     * @param  string  $title  the title of the expense
     * @param  string  $date  the day it was paid, `yyyy-mm-dd`
     * @param  float  $amount  the amount paid
     * @param  string  $occurrence  the original date of the due date it pays
     * @param  array<string, mixed>  $columns  the columns that differ from the planned expense
     */
    private function pay(PlannedExpense $plan, string $title, string $date, float $amount, string $occurrence, array $columns = []): Expense
    {
        return Expense::query()->create([
            'title' => $title, 'amount' => $amount, 'date' => $date, 'planned_expense_id' => $plan->id, 'occurrence_date' => $occurrence,
            'category_id' => $plan->category_id, 'organization_id' => $plan->organization_id,
            'financial_account_id' => $plan->financial_account_id, 'person_id' => $plan->person_id,
            ...$columns,
        ]);
    }

    /**
     * An income that settles one due date of a planned income, with its amount and the day it is due.
     *
     * @param  PlannedIncome  $plan  the planned income received
     * @param  string  $title  the title of the income
     * @param  string  $date  the day it arrived and was due, `yyyy-mm-dd`
     */
    private function receive(PlannedIncome $plan, string $title, string $date): Income
    {
        return Income::query()->create([
            'title' => $title, 'amount' => $plan->amount, 'date' => $date, 'type' => $plan->type, 'planned_income_id' => $plan->id,
            'occurrence_date' => $date, 'organization_id' => $plan->organization_id, 'person_id' => $plan->person_id,
            'financial_account_id' => $plan->financial_account_id, 'employment_id' => $plan->employment_id,
        ]);
    }

    /**
     * The id of an expense category of the family.
     *
     * @param  string  $path  the name, with the main category in front if it is a subcategory
     */
    private function categoryId(string $path): int
    {
        return $this->categoryIn(ExpenseCategory::query()->orderBy('sort'), $path)->id;
    }

    /**
     * Puts a fake file of `database/seeders/files/` in the store.
     *
     * @param  string  $name  the name of the file in the folder
     */
    private function file(string $name): File
    {
        $path = __DIR__.'/files/'.$name;

        return $this->files->store(new UploadedFile($path, $name, (string) mime_content_type($path), null, true));
    }

    /**
     * The document category written as `Main › Subcategory`, among those of the family.
     *
     * @param  string  $path  the name, with the main category in front if it is a subcategory
     */
    private function category(string $path): DocumentCategory
    {
        return $this->categoryIn(DocumentCategory::query()->orderBy('sort'), $path);
    }

    /**
     * The category of a tree written as `Main › Subcategory`, among those of the family.
     *
     * @template T of DocumentCategory|ExpenseCategory
     *
     * @param  Builder<T>  $categories  the query of the tree
     * @param  string  $path  the name, with the main category in front if it is a subcategory
     * @return T
     */
    private function categoryIn(Builder $categories, string $path): DocumentCategory|ExpenseCategory
    {
        $names = explode(' › ', $path);
        $main = (clone $categories)->whereNull('parent_id')->where('name', $names[0])->sole();

        return isset($names[1])
            ? (clone $categories)->where('parent_id', $main->getKey())->where('name', $names[1])->sole()
            : $main;
    }
}
