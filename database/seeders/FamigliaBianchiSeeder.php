<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Famiglie\CreaFamiglia;
use App\Enums\Ruolo;
use App\Enums\RuoloPersona;
use App\Enums\StatoDocumento;
use App\Enums\TipoAbbonamento;
use App\Enums\TipoConto;
use App\Enums\TipoEnte;
use App\Enums\TipoEventoSalute;
use App\Enums\TipoIscrizione;
use App\Enums\TipoPolizza;
use App\Enums\TipoPratica;
use App\Enums\TipoUtenza;
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
use App\Models\Organization;
use App\Models\Person;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\ArchivioFile;
use App\Support\CurrentFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * La famiglia Bianchi della tela: persone, account, enti, schede e documenti, con file finti.
 *
 * Gli account hanno la password di prova `password`, scritta nel `README.md`. Se
 * l'account di Marco c'è già il seeder si ferma, così lanciato due volte non duplica.
 */
final class FamigliaBianchiSeeder extends Seeder
{
    /** La password di prova degli account dei Bianchi. */
    private const string PASSWORD = 'password';

    /** Gli enti: nome => tipo. */
    private const array ENTI = [
        'Edison' => TipoEnte::UtilityProvider,
        'Enel Energia' => TipoEnte::UtilityProvider,
        'Hera' => TipoEnte::UtilityProvider,
        'Fastweb' => TipoEnte::UtilityProvider,
        'BPER Banca' => TipoEnte::Bank,
        'Findomestic' => TipoEnte::Bank,
        'UnipolSai' => TipoEnte::Insurance,
        'Studio Rinaldi' => TipoEnte::Employer,
        'Comune di Bologna' => TipoEnte::PublicOffice,
        'CAF CGIL Bologna' => TipoEnte::Other,
        'Scuola Passo a Due' => TipoEnte::School,
        'Studio Ferri' => TipoEnte::Healthcare,
        'Dott.ssa Neri' => TipoEnte::Healthcare,
        'Netflix' => TipoEnte::Shop,
    ];

    /** @var array<string, Organization> gli enti creati, per nome */
    private array $enti = [];

    /** @var array<string, Person> le persone, per nome */
    private array $persone = [];

    /** @var array<string, Model> le schede, con il nome che si legge sulla tela */
    private array $schede = [];

    /**
     * @param  CreaFamiglia  $creaFamiglia  l'azione dell'iscrizione
     * @param  ArchivioFile  $archivio  l'archivio dove vanno i file finti
     */
    public function __construct(
        private readonly CreaFamiglia $creaFamiglia,
        private readonly ArchivioFile $archivio,
    ) {}

    /**
     * Crea la famiglia Bianchi, se non c'è già.
     */
    public function run(): void
    {
        if (User::query()->where('email', 'marco.bianchi@example.com')->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $marco = $this->creaFamiglia->handle('Bianchi', 'Marco', 'Bianchi', 'marco.bianchi@example.com', self::PASSWORD);

            CurrentFamily::per($marco->family_id, function () use ($marco): void {
                $this->creaPersone($marco);
                $this->creaEnti();
                $this->creaSchede();
                $this->creaDocumenti();
            });

            // I Bianchi hanno già fatto il Benvenuto: si entra dritti nella Home.
            $marco->family->update(['welcomed_at' => now()]);
        });
    }

    /**
     * Marco c'è già con l'iscrizione: si aggiungono Laura, con il suo account, e Giulia, senza.
     *
     * @param  User  $marco  l'account di Marco
     */
    private function creaPersone(User $marco): void
    {
        $this->persone['Marco'] = Person::query()->where('user_id', $marco->id)->sole();

        $laura = User::query()->create([
            'family_id' => $marco->family_id,
            'name' => 'Laura Bianchi',
            'email' => 'laura.bianchi@example.com',
            'password' => self::PASSWORD,
            'role' => Ruolo::Admin,
        ]);
        // Gli account di prova entrano subito, senza la conferma dell'email.
        User::query()->whereKey([$marco->id, $laura->id])->update(['email_verified_at' => now()]);

        $this->persone['Laura'] = Person::query()->create([
            'first_name' => 'Laura', 'last_name' => 'Bianchi', 'initials' => 'LB',
            'role' => RuoloPersona::Parent, 'user_id' => $laura->id, 'birth_date' => '1986-04-14',
        ]);
        $this->persone['Giulia'] = Person::query()->create([
            'first_name' => 'Giulia', 'last_name' => 'Bianchi', 'initials' => 'GB',
            'role' => RuoloPersona::Child, 'birth_date' => '2016-05-24',
        ]);
    }

    /**
     * I 14 enti della tela.
     */
    private function creaEnti(): void
    {
        foreach (self::ENTI as $nome => $tipo) {
            $this->enti[$nome] = Organization::query()->create(['name' => $nome, 'types' => [$tipo]]);
        }
    }

    /**
     * Le schede che i documenti e le pagine della tela nominano.
     */
    private function creaSchede(): void
    {
        $casa = Home::query()->create(['name' => 'Casa di via Roma', 'address' => 'Via Roma 12', 'city' => 'Bologna', 'country' => 'Italia', 'is_owned' => true]);
        $conto = FinancialAccount::query()->create(['name' => 'Conto BPER', 'type' => TipoConto::CheckingAccount, 'organization_id' => $this->enti['BPER Banca']->id, 'person_id' => $this->persone['Marco']->id, 'is_default' => true]);
        $panda = Vehicle::query()->create(['name' => 'Fiat Panda', 'brand' => 'Fiat', 'model' => 'Panda', 'plate_number' => 'GH 482 TK', 'registration_year' => 2019, 'is_owned' => true, 'person_id' => $this->persone['Marco']->id]);

        $this->schede = [
            'Casa di via Roma' => $casa,
            'Luce - Edison' => $this->utenza(TipoUtenza::Electricity, 'Edison', $casa),
            'Gas - Enel' => $this->utenza(TipoUtenza::Gas, 'Enel Energia', $casa),
            'Acqua - Hera' => $this->utenza(TipoUtenza::Water, 'Hera', $casa),
            'Internet - Fastweb' => $this->utenza(TipoUtenza::Internet, 'Fastweb', $casa),
            'Fiat Panda' => $panda,
            'RC auto Panda - UnipolSai' => InsurancePolicy::query()->create(['title' => 'RC auto Panda', 'type' => TipoPolizza::Car, 'organization_id' => $this->enti['UnipolSai']->id, 'person_id' => $this->persone['Marco']->id, 'vehicle_id' => $panda->id, 'start_date' => '2025-11-20', 'expiry_date' => '2026-11-20']),
            'Conto BPER' => $conto,
            'Mutuo casa' => Loan::query()->create(['name' => 'Mutuo casa', 'organization_id' => $this->enti['BPER Banca']->id, 'person_id' => $this->persone['Marco']->id, 'financial_account_id' => $conto->id, 'start_date' => '2018-06-01']),
            'Finanziamento Panda' => Loan::query()->create(['name' => 'Finanziamento Panda', 'organization_id' => $this->enti['Findomestic']->id, 'person_id' => $this->persone['Marco']->id, 'financial_account_id' => $conto->id, 'start_date' => '2024-03-01']),
            'Marco - Studio Rinaldi' => Employment::query()->create(['person_id' => $this->persone['Marco']->id, 'organization_id' => $this->enti['Studio Rinaldi']->id, 'job_title' => 'Geometra']),
            'Laura - Comune di Bologna' => Employment::query()->create(['person_id' => $this->persone['Laura']->id, 'organization_id' => $this->enti['Comune di Bologna']->id, 'job_title' => 'Impiegata']),
            'Netflix' => Subscription::query()->create(['name' => 'Netflix', 'type' => TipoAbbonamento::Streaming, 'organization_id' => $this->enti['Netflix']->id, 'person_id' => $this->persone['Laura']->id, 'financial_account_id' => $conto->id, 'is_auto_renew' => true]),
            'Danza Giulia' => Enrollment::query()->create(['person_id' => $this->persone['Giulia']->id, 'organization_id' => $this->enti['Scuola Passo a Due']->id, 'type' => TipoIscrizione::Sport, 'school_year' => '2026/27', 'class_name' => 'Danza']),
            'Visite di Giulia' => HealthEvent::query()->create(['title' => 'Visite di Giulia', 'type' => TipoEventoSalute::Visit, 'person_id' => $this->persone['Giulia']->id, 'organization_id' => $this->enti['Dott.ssa Neri']->id, 'date' => '2025-12-11']),
            'TV Samsung' => Belonging::query()->create(['name' => 'TV Samsung', 'brand' => 'Samsung', 'home_id' => $casa->id, 'purchase_date' => '2024-12-20', 'warranty_expiry_date' => '2026-12-20']),
            'ISEE 2027' => Filing::query()->create(['title' => 'ISEE 2027', 'type' => TipoPratica::Isee, 'reference_year' => 2027, 'organization_id' => $this->enti['CAF CGIL Bologna']->id]),
        ];
    }

    /**
     * Un'utenza della casa, con il suo fornitore.
     *
     * @param  TipoUtenza  $tipo  luce, gas, acqua o internet
     * @param  string  $ente  il nome del fornitore
     * @param  Home  $casa  la casa servita
     */
    private function utenza(TipoUtenza $tipo, string $ente, Home $casa): Utility
    {
        return Utility::query()->create(['type' => $tipo, 'organization_id' => $this->enti[$ente]->id, 'home_id' => $casa->id]);
    }

    /**
     * I 18 documenti della tela, ognuno con il suo file finto.
     */
    private function creaDocumenti(): void
    {
        // titolo, categoria (principale › sotto), persona, ente, data, scadenza, schede, file
        $documenti = [
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

        foreach ($documenti as [$titolo, $categoria, $persona, $ente, $data, $scadenza, $schede, $file]) {
            $documento = Document::query()->create([
                'title' => $titolo,
                'file_id' => $this->file($file)->id,
                'category_id' => $this->categoria($categoria)->id,
                'status' => StatoDocumento::Active,
                'person_id' => $persona === null ? null : $this->persone[$persona]->id,
                'organization_id' => $ente === null ? null : $this->enti[$ente]->id,
                'date' => $data,
                'expiry_date' => $scadenza,
            ]);

            foreach ($schede as $scheda) {
                $documento->morphedByMany($this->schede[$scheda]::class, 'documentable')->attach($this->schede[$scheda]);
            }
        }
    }

    /**
     * Mette nell'archivio un file finto di `database/seeders/files/`.
     *
     * @param  string  $nome  il nome del file nella cartella
     */
    private function file(string $nome): File
    {
        $percorso = __DIR__.'/files/'.$nome;

        return $this->archivio->salva(new UploadedFile($percorso, $nome, (string) mime_content_type($percorso), null, true));
    }

    /**
     * La categoria scritta come `Principale › Sottocategoria`, tra quelle della famiglia.
     *
     * @param  string  $percorso  il nome, con la principale davanti se è una sottocategoria
     */
    private function categoria(string $percorso): DocumentCategory
    {
        $nomi = explode(' › ', $percorso);
        $principale = DocumentCategory::query()->whereNull('parent_id')->where('name', $nomi[0])->sole();

        return isset($nomi[1])
            ? $principale->children()->where('name', $nomi[1])->sole()
            : $principale;
    }
}
