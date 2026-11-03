<?php

declare(strict_types=1);

use App\Data\Reading\DocumentProposalData;
use App\Data\Reading\Extraction;
use App\Data\Reading\FoundLineData;
use App\Enums\UtilityType;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\Family;
use App\Models\File;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Subscription;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Reading\DocumentProposal;
use App\Services\Reading\ReadingContext;
use App\Support\CurrentFamily;
use Database\Seeders\BianchiFamilySeeder;

/**
 * The proposal of a document, from a reading and the lists of a family.
 *
 * @param  string  $locale  the language of the texts
 */
function propose(Extraction $x, ReadingContext $context, string $locale = 'it'): DocumentProposalData
{
    return resolve(DocumentProposal::class)->of($x, $context, $locale);
}

/**
 * A reading with only the given fields.
 *
 * @param  array<string, mixed>  $fields
 */
function reading(array $fields = []): Extraction
{
    return new Extraction(...['kind' => 'other', 'confidence' => 'high', ...$fields]);
}

/**
 * An empty set of lists, for the cases that need no family.
 */
function emptyContext(): ReadingContext
{
    return new ReadingContext([], [], [], [], null, null);
}

/**
 * The id of the entry of a list whose label is the given one.
 *
 * @param  list<array{id: int, label: string}>  $choices
 */
function idOf(array $choices, string $label): int
{
    return collect($choices)->firstWhere('label', $label)['id'];
}

beforeEach(function (): void {
    $this->bianchi = Family::factory()->create(['name' => 'Alfa']);
    $this->rossi = Family::factory()->create(['name' => 'Beta']);
});

afterEach(fn () => CurrentFamily::clear());

describe('dates', function (): void {
    test('monthYear writes the Italian month and the year', function (): void {
        expect(propose(reading(['title' => 'Carta', 'issue_date' => '2026-03-15']), emptyContext())->title)->toBe('Carta marzo 2026')
            ->and(propose(reading(['title' => 'Carta', 'issue_date' => '2026-12-01']), emptyContext())->title)->toBe('Carta dicembre 2026');
    });

    test('monthYear writes the English month in English', function (): void {
        expect(propose(reading(['title' => 'Card', 'issue_date' => '2026-03-15']), emptyContext(), 'en')->title)->toBe('Card March 2026');
    });

    test('formatDate writes day/month/year', function (): void {
        $proposal = propose(reading(['due_date' => '2026-03-15']), emptyContext());

        expect($proposal->found)->toEqual([new FoundLineData('Entro il', '15/03/2026')]);
    });

    test('a day that does not exist is no date', function (): void {
        $proposal = propose(reading(['title' => 'Carta', 'issue_date' => '2026-02-30', 'due_date' => '2026-13-01', 'expiry_date' => '2026-04-31']), emptyContext());

        expect($proposal->date)->toBeNull()
            ->and($proposal->expiry_date)->toBeNull()
            ->and($proposal->title)->toBe('Carta')
            ->and($proposal->found)->toBe([]);
    });

    test('the date is the issue date, otherwise the due date', function (): void {
        expect(propose(reading(['issue_date' => '2026-03-15', 'due_date' => '2026-04-01']), emptyContext())->date)->toBe('2026-03-15')
            ->and(propose(reading(['due_date' => '2026-04-01']), emptyContext())->date)->toBe('2026-04-01');
    });
});

describe('euro', function (): void {
    test('84.3 is written 84,30 € in both languages', function (): void {
        $it = propose(reading(['total_amount' => 84.3]), emptyContext(), 'it');
        $en = propose(reading(['total_amount' => 84.3]), emptyContext(), 'en');

        expect(preg_replace('/\s/u', ' ', $it->found[0]->value))->toBe('84,30 €')
            ->and(preg_replace('/\s/u', ' ', $en->found[0]->value))->toBe('84,30 €');
    });
});

describe('the document profile', function (): void {
    test('document: an "other" paper gives the title and the category, and no expense', function (): void {
        $category = CurrentFamily::using($this->bianchi->id, fn (): DocumentCategory => DocumentCategory::factory()->for($this->bianchi)->create(['name' => 'Identità']));
        $context = new ReadingContext([], [['id' => $category->id, 'label' => 'Identità']], [], [], null, null);

        $proposal = propose(reading(['title' => "Carta d'identità", 'issue_date' => '2026-03-15', 'document_category_id' => $category->id, 'total_amount' => 12]), $context);

        expect($proposal->title)->toBe("Carta d'identità marzo 2026")
            ->and($proposal->category_id)->toBe($category->id)
            ->and($proposal->links)->toBe([]);
    });

    test('an id that is not in the lists of the prompt is dropped', function (): void {
        $proposal = propose(reading(['document_category_id' => 99, 'organization_id' => 98, 'person_id' => 97]), emptyContext());

        expect($proposal->category_id)->toBeNull()
            ->and($proposal->organization_id)->toBeNull()
            ->and($proposal->person_id)->toBeNull();
    });

    test('the organization comes from the name when the model chose none', function (): void {
        $organization = CurrentFamily::using($this->bianchi->id, fn () => Organization::factory()->for($this->bianchi)->create(['name' => 'Studio Ferri S.r.l.']));
        $context = new ReadingContext([], [], [['id' => $organization->id, 'label' => 'Studio Ferri S.r.l.']], [], null, null, $this->bianchi->id);

        expect(propose(reading(['issuer_name' => 'STUDIO FERRI']), $context)->organization_id)->toBe($organization->id);
    });
});

describe('the expiry of a document', function (): void {
    test('%s: no expiry, whatever the reading says', function (string $kind): void {
        expect(propose(reading(['kind' => $kind, 'expiry_date' => '2026-10-03']), emptyContext())->expiry_date)->toBeNull();
    })->with(Extraction::NO_EXPIRY);

    test('%s: the expiry printed on the document stays', function (string $kind): void {
        expect(propose(reading(['kind' => $kind, 'expiry_date' => '2031-05-12']), emptyContext())->expiry_date)->toBe('2031-05-12');
    })->with(['other', 'insurance', 'medical']);

    test('the kinds without expiry are the six of the Directus profiles', function (): void {
        expect(Extraction::NO_EXPIRY)->toBe(['invoice', 'receipt', 'utility_bill', 'payment_receipt', 'payslip', 'road_tax']);
    });
});

describe('the Bianchi examples', function (): void {
    beforeEach(function (): void {
        $this->seed(BianchiFamilySeeder::class);
        $this->bianchi = Family::query()->where('name', 'Bianchi')->sole();
        $this->file = File::factory()->for($this->bianchi)->create(['original_name' => 'scan.pdf']);
        $this->context = ReadingContext::for($this->file);
    });

    test('the Edison bill with the POD links Luce - Edison and proposes the whole document', function (): void {
        $luce = insideBianchi(function (): Utility {
            $luce = Utility::query()->whereRelation('organization', 'name', 'Edison')->sole();
            $luce->update(['supply_point_code' => 'IT 001 E 12345678']);

            return $luce;
        });
        $edison = idOf($this->context->organizations, 'Edison');
        $category = idOf($this->context->documentCategories, 'Casa e utenze › Luce');

        $proposal = propose(reading([
            'kind' => 'utility_bill', 'issuer_name' => 'Edison Energia S.p.A.', 'total_amount' => 84.2, 'issue_date' => '2026-10-20',
            'due_date' => '2026-11-06', 'supply_point_code' => 'IT001E12345678', 'document_category_id' => $category, 'utility_type' => 'electricity',
        ]), $this->context);

        expect($proposal->links)->toBe([['type' => 'utility', 'id' => $luce->id]])
            ->and($proposal->title)->toBe('Bolletta luce ottobre 2026')
            ->and($proposal->category_id)->toBe($category)
            ->and($proposal->organization_id)->toBe($edison)
            ->and($proposal->date)->toBe('2026-10-20')
            ->and($proposal->expiry_date)->toBeNull()
            ->and($proposal->headline)->toBe('Ho trovato una bolletta della luce')
            ->and(array_map(fn (FoundLineData $line): string => $line->label, $proposal->found))->toBe(['Da chi arriva', 'Quanto', 'Entro il'])
            ->and($proposal->found[0]->value)->toBe('Edison')
            ->and(preg_replace('/\s/u', ' ', $proposal->found[1]->value))->toBe('84,20 €')
            ->and($proposal->found[2]->value)->toBe('06/11/2026');
    });

    test('the same bill in English', function (): void {
        insideBianchi(fn () => Utility::query()->whereRelation('organization', 'name', 'Edison')->sole()->update(['supply_point_code' => 'IT001E12345678']));

        $proposal = propose(reading(['kind' => 'utility_bill', 'issue_date' => '2026-10-20', 'due_date' => '2026-11-06', 'supply_point_code' => 'IT001E12345678']), $this->context, 'en');

        expect($proposal->title)->toBe('Electricity bill October 2026')
            ->and($proposal->headline)->toBe('I found an electricity bill')
            ->and($proposal->found[1])->toEqual(new FoundLineData('Due on', '06/11/2026'));
    });

    test('the registration certificate with the plate GH 482 TK links Fiat Panda', function (): void {
        $panda = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Fiat Panda')->sole());
        $category = idOf($this->context->documentCategories, 'Veicoli');

        $proposal = propose(reading([
            'kind' => 'other', 'title' => 'Libretto di circolazione Fiat Panda', 'plate_number' => 'gh482tk', 'document_category_id' => $category,
        ]), $this->context);

        expect($proposal->links)->toBe([['type' => 'vehicle', 'id' => $panda->id]])
            ->and($proposal->title)->toBe('Libretto di circolazione Fiat Panda')
            ->and($proposal->category_id)->toBe($category)
            ->and($proposal->headline)->toBe('Ho trovato un documento');
    });

    test('a policy of a car links the policy and its vehicle, and tells the holder and the issuer', function (): void {
        [$policy, $panda, $marco] = insideBianchi(fn (): array => [
            InsurancePolicy::query()->where('title', 'RC auto Panda')->sole(),
            Vehicle::query()->where('name', 'Fiat Panda')->sole(),
            Person::query()->where('first_name', 'Marco')->sole(),
        ]);
        $policy->updateQuietly(['policy_number' => 'RCA-123']);

        $proposal = propose(reading(['kind' => 'insurance', 'title' => 'Polizza', 'policy_number' => 'rca 123']), $this->context);

        expect($proposal->links)->toBe([['type' => 'vehicle', 'id' => $panda->id], ['type' => 'insurance_policy', 'id' => $policy->id]])
            ->and($proposal->person_id)->toBe($marco->id)
            ->and($proposal->organization_id)->toBe($policy->organization_id)
            ->and($proposal->headline)->toBe('Ho trovato un documento di assicurazione');
    });

    test('the dentist invoice with the note "dentista Giulia" is about Giulia', function (): void {
        $giulia = idOf($this->context->people, 'Giulia Bianchi');

        $proposal = propose(reading(['kind' => 'medical', 'title' => 'Fattura visita dentistica di Giulia', 'person_id' => $giulia]), $this->context);

        expect($proposal->person_id)->toBe($giulia)
            ->and($proposal->title)->toBe('Fattura visita dentistica di Giulia')
            ->and($proposal->headline)->toBe('Ho trovato un documento medico');
    });

    test('a payslip links the employment of the person and the title is the month of the period', function (): void {
        $laura = idOf($this->context->people, 'Laura Bianchi');
        $comune = idOf($this->context->organizations, 'Comune di Bologna');
        $job = insideBianchi(fn () => Employment::query()->where('person_id', $laura)->sole());

        $proposal = propose(reading([
            'kind' => 'payslip', 'person_id' => $laura, 'organization_id' => $comune, 'issue_date' => '2026-10-23', 'period_start' => '2026-09-01', 'expiry_date' => '2027-01-01',
        ]), $this->context);

        expect($proposal->links)->toBe([['type' => 'employment', 'id' => $job->id]])
            ->and($proposal->title)->toBe('Busta paga settembre 2026')
            ->and($proposal->expiry_date)->toBeNull()
            ->and($proposal->headline)->toBe('Ho trovato una busta paga');
    });

    test('an invoice of the only subscription of the issuer links the subscription', function (): void {
        $netflix = insideBianchi(fn (): Subscription => Subscription::query()->where('name', 'Netflix')->sole());

        $proposal = propose(reading(['kind' => 'invoice', 'organization_id' => $netflix->organization_id, 'issue_date' => '2026-10-05']), $this->context);

        expect($proposal->links)->toBe([['type' => 'subscription', 'id' => $netflix->id]])
            ->and($proposal->title)->toBe('Fattura Netflix ottobre 2026');
    });

    test('a bill of a utility type without the utility still says what it is', function (): void {
        $proposal = propose(reading(['kind' => 'utility_bill', 'issuer_name' => 'Acqua SpA', 'utility_type' => 'water', 'issue_date' => '2026-10-02']), $this->context);

        expect($proposal->links)->toBe([])
            ->and($proposal->title)->toBe('Bolletta acqua ottobre 2026')
            ->and($proposal->headline)->toBe('Ho trovato una bolletta dell\'acqua');
    });

    test('every kind and every utility type has a headline', function (): void {
        foreach (Extraction::KINDS as $kind) {
            expect(propose(reading(['kind' => $kind]), $this->context)->headline)->toStartWith('Ho trovato');
        }
        foreach (UtilityType::cases() as $type) {
            expect(propose(reading(['kind' => 'utility_bill', 'utility_type' => $type->value]), $this->context)->headline)->toStartWith('Ho trovato una bolletta');
        }
    });
});

describe('the family of the links', function (): void {
    test('a POD of another family links nothing, even when that family is the current one', function (): void {
        CurrentFamily::using($this->rossi->id, fn () => Utility::factory()->for($this->rossi)->create(['supply_point_code' => 'IT001E12345678']));
        $file = File::factory()->for($this->bianchi)->create();
        $context = ReadingContext::for($file);

        CurrentFamily::set($this->rossi->id);
        $proposal = propose(reading(['kind' => 'utility_bill', 'supply_point_code' => 'IT001E12345678']), $context);

        expect($proposal->links)->toBe([])
            ->and(CurrentFamily::id())->toBe($this->rossi->id);
    });

    test('the POD of the family of the file is found even when another family is the current one', function (): void {
        $utility = CurrentFamily::using($this->bianchi->id, fn (): Utility => Utility::factory()->for($this->bianchi)->create(['supply_point_code' => 'IT001E12345678']));
        $context = ReadingContext::for(File::factory()->for($this->bianchi)->create());

        CurrentFamily::set($this->rossi->id);

        expect(propose(reading(['kind' => 'utility_bill', 'supply_point_code' => 'IT001E12345678']), $context)->links)->toBe([['type' => 'utility', 'id' => $utility->id]]);
    });
});
