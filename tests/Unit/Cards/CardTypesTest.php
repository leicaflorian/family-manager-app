<?php

declare(strict_types=1);

use App\Cards\CardDate;
use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Cards\FieldKind;
use App\Enums\Area;
use App\Enums\InsurancePolicyStatus;
use App\Enums\UtilityType;
use App\Models\FinancialAccount;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\MaintenanceRecord;
use App\Models\Utility;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * The columns the person never fills in: the key, the family, the status (it
 * has a default) and the system fields of Laravel.
 */
const NOT_ASKED = ['id', 'family_id', 'status', 'created_at', 'updated_at', 'created_by', 'updated_by'];

/**
 * The fields the canvas marks as required although their column is nullable:
 * where the code and the canvas differ, the canvas wins (`Modifica-pc` has
 * «Titolo *» on a policy).
 */
const REQUIRED_BY_THE_CANVAS = ['insurance_policy' => ['title']];

/**
 * The type of a card, which the test expects to be there.
 *
 * @param  string  $type  the type of the morph map
 */
function cardType(string $type): CardType
{
    $card = CardTypes::find($type);
    expect($card)->not->toBeNull();
    assert($card instanceof CardType);

    return $card;
}

/**
 * A field of a card type, by its column.
 *
 * @param  string  $type  the type of the morph map
 * @param  string  $column  the column of the field
 */
function cardField(string $type, string $column): ?CardField
{
    return collect(cardType($type)->fields())->first(fn (CardField $field): bool => $field->column === $column);
}

test('it has the 13 card types in the areas of the canvas', function (): void {
    $types = fn (Area $area): array => array_map(fn (CardType $card): string => $card->type(), CardTypes::of($area));

    expect(array_map(fn (CardType $card): string => $card->type(), CardTypes::all()))->toBe([
        'home', 'utility', 'belonging', 'maintenance_record', 'vehicle', 'insurance_policy', 'financial_account',
        'loan', 'subscription', 'filing', 'employment', 'health_event', 'enrollment',
    ])
        ->and(array_map(fn (Area $area): string => $area->value, Area::cases()))->toBe([
            'home', 'vehicles', 'insurance', 'banks', 'health', 'school', 'work', 'subscriptions', 'paperwork',
        ])
        ->and(cardType('maintenance_record')->area())->toBe(Area::Home)
        ->and($types(Area::Home))->toBe(['home', 'utility', 'belonging', 'maintenance_record'])
        ->and($types(Area::Vehicles))->toBe(['vehicle'])
        ->and($types(Area::Insurance))->toBe(['insurance_policy'])
        ->and($types(Area::Banks))->toBe(['financial_account', 'loan'])
        ->and($types(Area::Health))->toBe(['health_event'])
        ->and($types(Area::School))->toBe(['enrollment'])
        ->and($types(Area::Work))->toBe(['employment'])
        ->and($types(Area::Subscriptions))->toBe(['subscription'])
        ->and($types(Area::Paperwork))->toBe(['filing'])
        ->and(cardType('insurance_policy')->model())->toBe(InsurancePolicy::class);
});

test('it names the types and the areas as the canvas does', function (): void {
    app()->setLocale('it');

    expect(cardType('insurance_policy')->label())->toBe('Polizza')
        ->and(cardType('insurance_policy')->description())->toBe("Dell'auto, della casa, vita, salute")
        ->and(cardType('belonging')->label())->toBe('Bene in garanzia')
        ->and(Area::Banks->label())->toBe('Banche e finanziamenti')
        ->and(cardField('utility', 'supply_point_code')?->label)->toBe('Codice POD')
        ->and(cardField('insurance_policy', 'person_id')?->label)->toBe('Contraente')
        ->and(cardField('insurance_policy', 'organization_id')?->label)->toBe('Compagnia')
        ->and(cardField('financial_account', 'last_four_digits')?->label)->toBe('Ultime 4 cifre');
});

test('it requires exactly the columns the database requires', function (): void {
    foreach (CardTypes::all() as $card) {
        $required = collect($card->fields())
            ->filter(fn (CardField $field): bool => $field->required && ! $field->virtual && $field->showWhen === [])
            ->map(fn (CardField $field): string => $field->column)
            ->sort()->values()->all();

        $notNull = collect(Schema::getColumns((new ($card->model()))->getTable()))
            ->filter(fn (array $column): bool => ! $column['nullable'] && $column['default'] === null && ! $column['auto_increment'])
            ->map(fn (array $column): string => $column['name'])
            ->reject(fn (string $name): bool => in_array($name, NOT_ASKED, true))
            ->merge(REQUIRED_BY_THE_CANVAS[$card->type()] ?? [])
            ->sort()->values()->all();

        expect($required)->toBe($notNull, $card->type());
    }
});

test('every field is a column of its table, and every column the person fills in is a field', function (): void {
    // Calculated by the app or filled in from the expenses of phase 3.
    $notFields = ['total_to_pay', 'cost', 'installments_paid', 'installments_left', 'residual_amount', 'end_date', 'expense_id'];

    foreach (CardTypes::all() as $card) {
        $table = (new ($card->model()))->getTable();
        $columns = collect(Schema::getColumns($table))->map(fn (array $column): string => $column['name']);
        $fields = collect($card->fields())->reject(fn (CardField $field): bool => $field->virtual)
            ->map(fn (CardField $field): string => $field->column);

        $expected = $columns->reject(fn (string $name): bool => in_array($name, NOT_ASKED, true) && $name !== 'status')
            ->reject(fn (string $name): bool => $card->type() === 'loan' && in_array($name, $notFields, true))
            ->reject(fn (string $name): bool => $name === 'expense_id');

        $last = collect($card->fields())->last();

        expect($fields->sort()->values()->all())->toBe($expected->sort()->values()->all(), $card->type())
            ->and($last?->column)->toBe('notes', $card->type())
            ->and($last?->kind)->toBe(FieldKind::Textarea);

        foreach ($card->dates() as $date) {
            expect($columns->all())->toContain($date->column);
        }
    }
});

test('every choice has its enum, and every linked card a type of card', function (): void {
    foreach (CardTypes::all() as $card) {
        foreach ($card->fields() as $field) {
            match ($field->kind) {
                FieldKind::Choice, FieldKind::Dropdown => expect($field->enum)->not->toBeNull()
                    ->and(enum_exists((string) $field->enum))->toBeTrue(),
                FieldKind::Card => expect(CardTypes::find((string) $field->target))->not->toBeNull(),
                FieldKind::Person => expect($field->column)->toBe('person_id'),
                FieldKind::Organization => expect($field->column)->toBe('organization_id'),
                default => expect($field->enum)->toBeNull(),
            };
        }
    }
});

test('it names relations to other cards', function (): void {
    $models = array_map(fn (CardType $card): string => $card->model(), CardTypes::all());

    foreach (CardTypes::all() as $card) {
        foreach ($card->related() as $relation) {
            $instance = (new ($card->model()))->{$relation}();

            expect($instance)->toBeInstanceOf(Relation::class)
                ->and($models)->toContain($instance->getRelated()::class);
        }
    }

    expect(cardType('home')->related())->toContain('utilities', 'belongings', 'maintenanceRecords')
        ->and(cardType('financial_account')->related())->toContain('loans', 'subscriptions')
        ->and(cardType('employment')->related())->toContain('insurancePolicies')
        ->and(cardType('vehicle')->related())->toContain('insurancePolicies', 'maintenanceRecords')
        ->and(cardType('belonging')->related())->toContain('maintenanceRecords');
});

test('it never offers the computed fields of a loan', function (): void {
    $columns = array_map(fn (CardField $field): string => $field->column, cardType('loan')->fields());

    foreach (['total_to_pay', 'cost', 'installments_paid', 'installments_left', 'residual_amount', 'end_date'] as $computed) {
        expect($columns)->not->toContain($computed);
    }

    expect(array_map(fn (CardDate $date): string => $date->column, cardType('loan')->dates()))->toContain('end_date');
});

test('it asks the linked record by the type of policy', function (): void {
    expect(cardField('insurance_policy', 'vehicle_id')?->showWhen)->toBe(['type' => ['car']])
        ->and(cardField('insurance_policy', 'vehicle_id')?->target)->toBe('vehicle')
        ->and(cardField('insurance_policy', 'home_id')?->showWhen)->toBe(['type' => ['home']])
        ->and(cardField('insurance_policy', 'employment_id')?->showWhen)->toBe(['type' => ['health', 'accident', 'fund']]);
});

test('it asks the supply point code only for electricity and gas', function (): void {
    expect(cardField('utility', 'supply_point_code')?->showWhen)->toBe(['type' => ['electricity', 'gas']])
        ->and(cardField('utility', 'customer_code')?->showWhen)->toBe([]);
});

test('it names the supply point code POD for electricity and PDR for gas', function (): void {
    app()->setLocale('it');

    expect(cardField('utility', 'supply_point_code')?->labelFor(new Utility(['type' => UtilityType::Electricity])))->toBe('Codice POD')
        ->and(cardField('utility', 'supply_point_code')?->labelFor(new Utility(['type' => UtilityType::Gas])))->toBe('Codice PDR')
        ->and(cardField('utility', 'customer_code')?->labelFor(new Utility(['type' => UtilityType::Gas])))->toBe('Codice cliente');
});

test('it links a maintenance to one thing only', function (): void {
    $target = cardField('maintenance_record', 'target');

    expect($target?->virtual)->toBeTrue()
        ->and($target?->kind)->toBe(FieldKind::Choice)
        ->and($target?->required)->toBeTrue()
        ->and(array_map(fn (BackedEnum $case): string|int => $case->value, ($target?->enum ?? '')::cases()))
        ->toBe(['home', 'belonging', 'vehicle']);

    foreach (['home', 'belonging', 'vehicle'] as $thing) {
        $field = cardField('maintenance_record', "{$thing}_id");

        expect($field?->required)->toBeTrue()
            ->and($field?->kind)->toBe(FieldKind::Card)
            ->and($field?->target)->toBe($thing)
            ->and($field?->showWhen)->toBe(['target' => [$thing]]);
    }
});

test('it treats only real deadlines as expiring', function (): void {
    $expiring = [];
    $all = [];

    foreach (CardTypes::all() as $card) {
        foreach ($card->dates() as $date) {
            $all[] = "{$card->type()}.{$date->column}";

            if ($date->expires) {
                $expiring[] = "{$card->type()}.{$date->column}";
            }
        }
    }

    expect($expiring)->toEqualCanonicalizing([
        'vehicle.road_tax_due_date', 'vehicle.inspection_due_date',
        'insurance_policy.expiry_date', 'belonging.warranty_expiry_date',
        'subscription.renewal_date', 'subscription.trial_end_date',
        'filing.due_date', 'health_event.next_checkup_date',
    ])
        ->and($all)->toContain('insurance_policy.start_date', 'belonging.purchase_date', 'maintenance_record.date', 'loan.end_date');
});

test('it closes a card by its status', function (): void {
    $policy = (new InsurancePolicy)->forceFill(['status' => InsurancePolicyStatus::Cancelled]);
    $active = (new InsurancePolicy)->forceFill(['status' => InsurancePolicyStatus::Active]);

    expect(cardType('insurance_policy')->isClosed($policy))->toBeTrue()
        ->and(cardType('insurance_policy')->isClosed($active))->toBeFalse()
        ->and(cardType('maintenance_record')->isClosed(new MaintenanceRecord))->toBeFalse()
        ->and(CardTypes::for($policy)->type())->toBe('insurance_policy')
        ->and(CardType::CLOSED)->toBe([
            'archived', 'expired', 'cancelled', 'closed', 'paid_off', 'terminated', 'ended', 'sold', 'disposed', 'rejected',
        ]);
});

test('it recognises a card by a piece of its data, and warns from the saved data', function (): void {
    app()->setLocale('it');

    $vehicle = (new Vehicle)->forceFill(['name' => 'Fiat Panda', 'plate_number' => 'GH 482 TK']);
    $home = (new Home)->forceFill(['name' => 'Casa di via Roma', 'address' => 'Via Roma 14', 'city' => 'Bologna']);
    $account = (new FinancialAccount)->forceFill(['name' => 'Conto BPER', 'is_default' => true]);
    $other = (new FinancialAccount)->forceFill(['name' => 'Carta Laura', 'is_default' => false]);
    $boiler = (new MaintenanceRecord)->forceFill(['title' => 'Revisione caldaia'])->setRelation('home', $home);

    expect(cardType('vehicle')->subtitle($vehicle))->toBe('GH 482 TK')
        ->and(cardType('home')->subtitle($home))->toBe('Via Roma 14, Bologna')
        ->and(cardType('financial_account')->notices($account))->toHaveCount(1)
        ->and(cardType('financial_account')->notices($account)[0])->toStartWith('È il conto predefinito')
        ->and(cardType('financial_account')->notices($other))->toBe([])
        ->and(cardType('maintenance_record')->notices($boiler)[0])->toStartWith('Vale per una cosa sola')
        ->and(cardType('maintenance_record')->notices($boiler)[0])->toContain('Casa di via Roma')
        ->and(cardType('vehicle')->notices($vehicle))->toBe([]);
});

test('it finds no unknown type', function (): void {
    expect(CardTypes::find('spaceship'))->toBeNull();
});

test('it gives the card types a new card can be linked to, with the values that link it', function (string $type, array $expected): void {
    $addable = array_map(
        static fn (array $entry): array => [$entry['type']->type(), $entry['preset']],
        CardTypes::addableTo(cardType($type), 5),
    );

    expect($addable)->toBe($expected);
})->with([
    'vehicle' => ['vehicle', [
        ['maintenance_record', ['target' => 'vehicle', 'vehicle_id' => 5]],
        ['insurance_policy', ['type' => 'car', 'vehicle_id' => 5]],
    ]],
    'home' => ['home', [
        ['utility', ['home_id' => 5]],
        ['belonging', ['home_id' => 5]],
        ['maintenance_record', ['target' => 'home', 'home_id' => 5]],
        ['insurance_policy', ['type' => 'home', 'home_id' => 5]],
    ]],
    'belonging' => ['belonging', [['maintenance_record', ['target' => 'belonging', 'belonging_id' => 5]]]],
    'account' => ['financial_account', [
        ['loan', ['financial_account_id' => 5]],
        ['subscription', ['financial_account_id' => 5]],
    ]],
    // Three types of policy cover an employment: the person picks one, only the link is set.
    'employment' => ['employment', [['insurance_policy', ['employment_id' => 5]]]],
    'policy' => ['insurance_policy', []],
]);
