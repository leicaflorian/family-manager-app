<?php

declare(strict_types=1);

use App\Data\Reading\Extraction;
use App\Data\Reading\RecordDraftData;
use App\Enums\RecordKind;
use App\Models\Belonging;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\MaintenanceRecord;
use App\Models\Organization;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Reading\RecordDrafts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A reading with only the given fields.
 *
 * @param  array<string, mixed>  $fields
 */
function draftReading(array $fields = []): Extraction
{
    return new Extraction(...['kind' => 'other', 'confidence' => 'high', ...$fields]);
}

/**
 * A model with the given attributes, not saved.
 *
 * @template T of Model
 *
 * @param  class-string<T>  $class
 * @param  array<string, mixed>  $attributes
 * @return T
 */
function unsaved(string $class, array $attributes): Model
{
    return (new $class)->forceFill($attributes);
}

/**
 * What the searches found: nothing, with one active home, unless overridden.
 *
 * @param  array<string, mixed>  $found
 * @return array{utility: ?Utility, terminatedUtility: ?Utility, vehicle: ?Vehicle, policy: ?InsurancePolicy, organization: ?Organization, belonging: ?Belonging, maintenance: ?MaintenanceRecord, homes: Collection<int, Home>}
 */
function foundNothing(array $found = []): array
{
    return $found + [
        'utility' => null, 'terminatedUtility' => null, 'vehicle' => null, 'policy' => null, 'organization' => null,
        'belonging' => null, 'maintenance' => null,
        'homes' => new Collection([unsaved(Home::class, ['id' => 1, 'name' => 'Casa Schio'])]),
    ];
}

/**
 * The document as the person will save it.
 *
 * @param  array<string, mixed>  $document
 * @return array{organization_id: ?int, organization_name: ?string, person_id: ?int, date: ?CarbonImmutable}
 */
function draftDocument(array $document = []): array
{
    return $document + ['organization_id' => null, 'organization_name' => null, 'person_id' => 2, 'date' => CarbonImmutable::parse('2026-10-01')];
}

/**
 * The drafts of a reading.
 *
 * @param  array<string, mixed>  $fields
 * @param  array<string, mixed>  $found
 * @param  array<string, mixed>  $document
 * @return list<RecordDraftData>
 */
function draftsOf(array $fields, array $found = [], array $document = [], ?float $amount = null): array
{
    return RecordDrafts::of(draftReading($fields), foundNothing($found), draftDocument($document), $amount);
}

/**
 * The kinds of the drafts, as strings.
 *
 * @param  list<RecordDraftData>  $drafts
 * @return list<string>
 */
function draftKinds(array $drafts): array
{
    return array_map(static fn (RecordDraftData $draft): string => $draft->kind->value, $drafts);
}

/**
 * The draft of a kind.
 *
 * @param  list<RecordDraftData>  $drafts
 */
function draftOf(array $drafts, string $kind): RecordDraftData
{
    foreach ($drafts as $draft) {
        if ($draft->kind->value === $kind) {
            return $draft;
        }
    }

    throw new RuntimeException("No {$kind} draft");
}

test('the Edison contract: a new organization and a new utility, both ticked, in creation order', function (): void {
    $drafts = draftsOf([
        'kind' => 'utility_bill', 'issuer_name' => 'Edison Energia S.p.A.', 'organization_type' => 'utility_provider',
        'supply_point_code' => 'IT001E12345678', 'customer_code' => '1002345678', 'utility_type' => 'electricity',
        'contract_start_date' => '2026-10-01',
    ]);

    expect(draftKinds($drafts))->toBe(['organization', 'utility'])
        ->and(draftOf($drafts, 'organization')->suggested)->toBeTrue()
        ->and(draftOf($drafts, 'organization')->title)->toBe('the organization Edison Energia S.p.A.')
        ->and(draftOf($drafts, 'organization')->values)->toBe(['name' => 'Edison Energia S.p.A.', 'types' => ['utility_provider']])
        ->and(draftOf($drafts, 'utility')->suggested)->toBeTrue()
        ->and(draftOf($drafts, 'utility')->missing)->toBe([])
        ->and(draftOf($drafts, 'utility')->title)->toBe('the utility Electricity · Edison Energia S.p.A.')
        ->and(draftOf($drafts, 'utility')->values)->toBe([
            'type' => 'electricity', 'supply_point_code' => 'IT001E12345678', 'customer_code' => '1002345678', 'organization_id' => null,
            'person_id' => 2, 'home_id' => 1, 'activation_date' => '2026-10-01',
        ]);
});

test('the titles are written in Italian for an Italian person', function (): void {
    app()->setLocale('it');

    $drafts = draftsOf([
        'kind' => 'utility_bill', 'issuer_name' => 'Edison Energia S.p.A.', 'supply_point_code' => 'IT001E12345678', 'utility_type' => 'electricity',
    ]);

    expect(draftOf($drafts, 'utility')->title)->toBe("l'utenza Luce · Edison Energia S.p.A.")
        ->and(draftOf($drafts, 'organization')->title)->toBe("l'ente Edison Energia S.p.A.");
});

test('a utility found by its code is not proposed', function (): void {
    $drafts = draftsOf(
        ['kind' => 'utility_bill', 'supply_point_code' => 'IT001E12345678', 'utility_type' => 'electricity'],
        ['utility' => unsaved(Utility::class, ['id' => 4])],
        draftDocument(['organization_id' => 7]),
        72,
    );

    expect($drafts)->toBe([]);
});

test('a customer code on a shop invoice is not a utility', function (): void {
    expect(draftsOf(['kind' => 'invoice', 'customer_code' => 'C123'], [], draftDocument(['organization_id' => 7]), 30))->toBe([]);
});

test('a utility without its type is proposed unticked, saying what is missing', function (): void {
    $drafts = draftsOf(['kind' => 'utility_bill', 'supply_point_code' => 'IT001E1'], [], draftDocument(['organization_id' => 7]), 50);

    expect(draftOf($drafts, 'utility')->suggested)->toBeFalse()
        ->and(draftOf($drafts, 'utility')->missing)->toBe(['the type']);
});

test('a code only on a terminated utility: the new one takes its home and holder', function (): void {
    $found = [
        'terminatedUtility' => unsaved(Utility::class, ['id' => 9, 'home_id' => 5, 'person_id' => 8]),
        'homes' => new Collection([unsaved(Home::class, ['id' => 1]), unsaved(Home::class, ['id' => 5])]),
    ];
    $drafts = draftsOf(['kind' => 'utility_bill', 'supply_point_code' => 'IT001E1', 'utility_type' => 'gas'], $found, draftDocument(['organization_id' => 7]), 50);

    expect(draftOf($drafts, 'utility')->values)->toMatchArray(['home_id' => 5, 'person_id' => 8]);
});

test('two active homes: the home is left empty', function (): void {
    $found = ['homes' => new Collection([unsaved(Home::class, ['id' => 1]), unsaved(Home::class, ['id' => 5])])];
    $drafts = draftsOf(['kind' => 'utility_bill', 'supply_point_code' => 'IT001E1', 'utility_type' => 'gas'], $found, draftDocument(['organization_id' => 7]), 50);

    expect(draftOf($drafts, 'utility')->values['home_id'])->toBeNull();
});

test('the MediaWorld receipt of 03/10/2026, 549.00 euro: the belonging is ticked, warranty two years', function (): void {
    $drafts = draftsOf(
        ['kind' => 'receipt', 'durable_good' => true, 'title' => 'Lavatrice', 'item_name' => 'Lavatrice', 'item_brand' => 'Bosch', 'item_model' => 'WGG244'],
        [],
        draftDocument(['organization_id' => 9, 'organization_name' => 'MediaWorld', 'date' => CarbonImmutable::parse('2026-10-03')]),
        549,
    );

    expect(draftKinds($drafts))->toBe(['belonging'])
        ->and(draftOf($drafts, 'belonging')->suggested)->toBeTrue()
        ->and(draftOf($drafts, 'belonging')->title)->toBe('the item Lavatrice Bosch WGG244')
        ->and(draftOf($drafts, 'belonging')->values)->toBe([
            'name' => 'Lavatrice', 'brand' => 'Bosch', 'model' => 'WGG244', 'purchase_date' => '2026-10-03', 'purchase_price' => 549.0,
            'organization_id' => 9, 'home_id' => 1, 'warranty_expiry_date' => '2028-10-03',
        ]);
});

test('a blender at 39.90 euro: the belonging is there, not ticked', function (): void {
    $drafts = draftsOf(['kind' => 'receipt', 'durable_good' => true, 'item_name' => 'Frullatore'], [], draftDocument(['organization_id' => 9]), 39.9);

    expect(draftOf($drafts, 'belonging')->suggested)->toBeFalse();
});

test('exactly 100 euro is ticked', function (): void {
    $drafts = draftsOf(['kind' => 'receipt', 'durable_good' => true, 'item_name' => 'Trapano'], [], draftDocument(['organization_id' => 9]), 100);

    expect(draftOf($drafts, 'belonging')->suggested)->toBeTrue();
});

test('a belonging found is still drafted: the page links it', function (): void {
    $found = ['belonging' => unsaved(Belonging::class, ['id' => 1, 'name' => 'Lavatrice Bosch'])];
    $drafts = draftsOf(['kind' => 'receipt', 'durable_good' => true, 'item_name' => 'Lavatrice'], $found, draftDocument(['organization_id' => 9]), 549);

    expect(draftKinds($drafts))->toBe(['belonging'])
        ->and(draftOf($drafts, 'belonging')->existing)->toBe(['id' => 1, 'title' => 'Lavatrice Bosch']);
});

test('the plumber Rossi of 20/09/2026, 120.00 euro, boiler: an intervention on the home, and the new organization', function (): void {
    $drafts = draftsOf(
        ['kind' => 'invoice', 'maintenance_type' => 'boiler_service', 'title' => 'Manutenzione caldaia', 'issuer_name' => 'Idraulico Rossi'],
        [],
        draftDocument(['date' => CarbonImmutable::parse('2026-09-20')]),
        120,
    );

    expect(draftKinds($drafts))->toBe(['organization', 'maintenance'])
        ->and(draftOf($drafts, 'maintenance')->suggested)->toBeTrue()
        ->and(draftOf($drafts, 'maintenance')->title)->toBe('the intervention Manutenzione caldaia')
        ->and(draftOf($drafts, 'maintenance')->values)->toBe([
            'title' => 'Manutenzione caldaia', 'date' => '2026-09-20', 'type' => 'boiler_service', 'organization_id' => null,
            'vehicle_id' => null, 'home_id' => 1,
        ])
        ->and(draftOf($drafts, 'organization')->suggested)->toBeTrue();
});

test('an intervention on a car found by its plate goes on the car, not the home', function (): void {
    $found = ['vehicle' => unsaved(Vehicle::class, ['id' => 3])];
    $drafts = draftsOf(['kind' => 'invoice', 'maintenance_type' => 'service', 'plate_number' => 'AB123CD'], $found, draftDocument(['organization_id' => 7]), 250);

    expect(draftOf($drafts, 'maintenance')->values)->toMatchArray(['vehicle_id' => 3, 'home_id' => null, 'title' => 'Service']);
});

test('an intervention found is still drafted with the one to link', function (): void {
    $found = ['maintenance' => unsaved(MaintenanceRecord::class, ['id' => 6, 'title' => 'Tagliando', 'date' => CarbonImmutable::parse('2026-09-20')])];
    $drafts = draftsOf(['kind' => 'invoice', 'maintenance_type' => 'service'], $found, draftDocument(['organization_id' => 7]), 250);

    expect(draftOf($drafts, 'maintenance')->existing)->toBe(['id' => 6, 'title' => 'Tagliando (20/09/2026)']);
});

test('the supermarket receipt of 84.20 euro: nothing', function (): void {
    expect(draftsOf(['kind' => 'receipt', 'durable_good' => false, 'issuer_name' => 'Supermercato'], [], draftDocument(['organization_id' => 4]), 84.2))->toBe([]);
});

test('a shop not among the organizations, on a receipt: proposed unticked', function (): void {
    $drafts = draftsOf(['kind' => 'receipt', 'issuer_name' => 'Ferramenta Bianchi', 'organization_type' => 'shop'], [], [], 12);

    expect(draftOf($drafts, 'organization')->suggested)->toBeFalse()
        ->and(draftOf($drafts, 'organization')->values['types'])->toBe(['shop']);
});

test('an organization found by name, the document without one: proposed, ticked, to be linked', function (): void {
    $found = ['organization' => unsaved(Organization::class, ['id' => 3, 'name' => 'Idraulico Rossi'])];
    $drafts = draftsOf(['kind' => 'receipt', 'issuer_name' => 'Idraulico Rossi'], $found, [], 12);

    expect(draftKinds($drafts))->toBe(['organization'])
        ->and($drafts[0]->suggested)->toBeTrue()
        ->and($drafts[0]->existing)->toBe(['id' => 3, 'title' => 'Idraulico Rossi']);
});

test('the document has its organization already: none proposed', function (): void {
    $found = ['organization' => unsaved(Organization::class, ['id' => 3, 'name' => 'Enel'])];

    expect(draftsOf(['kind' => 'invoice', 'issuer_name' => 'Enel'], $found, draftDocument(['organization_id' => 3]), 12))->toBe([]);
});

test('a policy with a new plate: vehicle and policy, ticked', function (): void {
    $drafts = draftsOf(
        [
            'kind' => 'insurance', 'policy_number' => '123456', 'insurance_type' => 'car', 'plate_number' => 'AB123CD', 'vehicle_brand' => 'Renault',
            'vehicle_model' => 'Clio', 'vehicle_year' => 2019, 'contract_start_date' => '2026-10-01', 'expiry_date' => '2027-10-01',
        ],
        [],
        draftDocument(['organization_id' => 6, 'organization_name' => 'Allianz']),
        480,
    );

    expect(draftKinds($drafts))->toBe(['vehicle', 'policy'])
        ->and(draftOf($drafts, 'vehicle')->suggested)->toBeTrue()
        ->and(draftOf($drafts, 'vehicle')->title)->toBe('the vehicle Clio (AB123CD)')
        ->and(draftOf($drafts, 'vehicle')->values)->toBe([
            'name' => 'Clio', 'plate_number' => 'AB123CD', 'brand' => 'Renault', 'model' => 'Clio', 'registration_year' => 2019, 'person_id' => 2,
        ])
        ->and(draftOf($drafts, 'policy')->suggested)->toBeTrue()
        ->and(draftOf($drafts, 'policy')->title)->toBe('the policy 123456 · Allianz')
        ->and(draftOf($drafts, 'policy')->values)->toMatchArray([
            'title' => 'Car insurance', 'policy_number' => '123456', 'type' => 'car', 'organization_id' => 6, 'person_id' => 2,
            'start_date' => '2026-10-01', 'expiry_date' => '2027-10-01',
        ]);
});

test('a vehicle without a model is named by its plate', function (): void {
    $drafts = draftsOf(['kind' => 'road_tax', 'plate_number' => 'AB123CD'], [], draftDocument(['organization_id' => 4]), 200);

    expect(draftOf($drafts, 'vehicle')->title)->toBe('the vehicle AB123CD')
        ->and(draftOf($drafts, 'vehicle')->values['name'])->toBe('AB123CD');
});

test('the policy starts at the period when no contract date is printed, and takes the vehicle found', function (): void {
    $found = ['vehicle' => unsaved(Vehicle::class, ['id' => 3])];
    $drafts = draftsOf(['kind' => 'insurance', 'policy_number' => '9', 'insurance_type' => 'car', 'period_start' => '2026-11-01'], $found, draftDocument(['organization_id' => 6]), 480);

    expect(draftOf($drafts, 'policy')->values)->toMatchArray(['start_date' => '2026-11-01', 'vehicle_id' => 3]);
});

test('a new policy is titled after its type', function (): void {
    $title = static fn (?string $type): mixed => draftOf(
        draftsOf(['kind' => 'insurance', 'policy_number' => '1', 'insurance_type' => $type], [], draftDocument(['organization_id' => 6]), 100),
        'policy',
    )->values['title'];

    expect($title('car'))->toBe('Car insurance')
        ->and($title('life'))->toBe('Life insurance')
        ->and($title('home'))->toBe('Home insurance')
        ->and($title('health'))->toBe('Health insurance')
        ->and($title('accident'))->toBe('Accident policy')
        ->and($title('fund'))->toBe('Fund')
        ->and($title('other'))->toBe('Policy')
        ->and($title(null))->toBe('Policy');
});

test('a home policy takes the only active home', function (): void {
    $drafts = draftsOf(['kind' => 'insurance', 'policy_number' => 'H-77', 'insurance_type' => 'home'], [], draftDocument(['organization_id' => 6]), 320);

    expect(draftOf($drafts, 'policy')->values)->toMatchArray(['type' => 'home', 'home_id' => 1]);
});

test('a home policy with two active homes has no home: the user picks it', function (): void {
    $found = ['homes' => new Collection([unsaved(Home::class, ['id' => 1]), unsaved(Home::class, ['id' => 2])])];
    $drafts = draftsOf(['kind' => 'insurance', 'policy_number' => 'H-77', 'insurance_type' => 'home'], $found, draftDocument(['organization_id' => 6]), 320);

    expect(draftOf($drafts, 'policy')->values['home_id'])->toBeNull();
});

test('a policy of another type has no home', function (): void {
    $drafts = draftsOf(['kind' => 'insurance', 'policy_number' => '123456', 'insurance_type' => 'life'], [], draftDocument(['organization_id' => 6]), 320);

    expect(draftOf($drafts, 'policy')->values['home_id'])->toBeNull();
});

test('a plate on a supermarket receipt is not a vehicle', function (): void {
    expect(draftsOf(['kind' => 'receipt', 'plate_number' => 'AB123CD'], [], draftDocument(['organization_id' => 4]), 20))->toBe([]);
});

test('an uncertain reading ticks nothing', function (): void {
    $drafts = draftsOf(
        ['kind' => 'insurance', 'policy_number' => '123456', 'insurance_type' => 'car', 'confidence' => 'low'],
        [],
        draftDocument(['organization_id' => 6]),
        480,
    );

    expect($drafts)->not->toBe([])
        ->and(array_filter($drafts, static fn (RecordDraftData $draft): bool => $draft->suggested))->toBe([]);
});

test('the creation order', function (): void {
    expect(array_column(RecordKind::cases(), 'value'))->toBe(['organization', 'vehicle', 'utility', 'policy', 'belonging', 'maintenance']);
});
