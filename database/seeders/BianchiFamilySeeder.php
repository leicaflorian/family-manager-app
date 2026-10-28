<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Families\CreateFamily;
use App\Enums\DocumentStatus;
use App\Enums\EnrollmentType;
use App\Enums\FilingType;
use App\Enums\FinancialAccountType;
use App\Enums\HealthEventType;
use App\Enums\InsurancePolicyType;
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
use App\Models\File;
use App\Models\Filing;
use App\Models\FinancialAccount;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\FileStore;
use App\Support\CurrentFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;

/**
 * The Bianchi family of the canvas: people, accounts, organizations, records and
 * documents, with fake files.
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
    ];

    /** @var array<string, Organization> the organizations created, by name */
    private array $organizations = [];

    /** @var array<string, Person> the people, by first name */
    private array $people = [];

    /** @var array<string, Model> the records, with the name read on the canvas */
    private array $records = [];

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
     * The 15 organizations of the canvas.
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
        $account = FinancialAccount::query()->create(['name' => 'Conto BPER', 'type' => FinancialAccountType::CheckingAccount, 'organization_id' => $this->organizations['BPER Banca']->id, 'person_id' => $this->people['Marco']->id, 'is_default' => true]);
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
            'Finanziamento Panda' => Loan::query()->create(['name' => 'Finanziamento Panda', 'organization_id' => $this->organizations['Findomestic']->id, 'person_id' => $this->people['Marco']->id, 'financial_account_id' => $account->id, 'start_date' => '2024-03-01']),
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
            ['Bolletta luce di settembre', 'Casa e utenze › Luce', null, 'Edison', '2026-09-25', null, ['Luce - Edison'], 'bolletta-luce-settembre.pdf'],
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
     * The category written as `Main › Subcategory`, among those of the family.
     *
     * @param  string  $path  the name, with the main category in front if it is a subcategory
     */
    private function category(string $path): DocumentCategory
    {
        $names = explode(' › ', $path);
        $main = DocumentCategory::query()->whereNull('parent_id')->where('name', $names[0])->sole();

        return isset($names[1])
            ? $main->children()->where('name', $names[1])->sole()
            : $main;
    }
}
