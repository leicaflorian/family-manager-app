<?php

declare(strict_types=1);

use App\Cards\CardTypes;
use App\Data\CardData;
use App\Data\CardDeadlineData;
use App\Data\CardValueData;
use App\Data\DocumentData;
use App\Enums\Role;
use App\Enums\UtilityType;
use App\Models\Family;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\CardDetails;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2026-10-26 10:00:00');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
    $this->actingAs($this->marco);
    $this->panda = insideBianchi(fn (): Vehicle => Vehicle::query()->where('name', 'Fiat Panda')->sole());
    $this->policy = insideBianchi(fn (): InsurancePolicy => InsurancePolicy::query()->where('title', 'RC auto Panda')->sole());
    $this->details = resolve(CardDetails::class);
});

test('it shows the data of a policy with labels and links', function (): void {
    $values = collect(insideBianchi(fn () => $this->details->of($this->policy, $this->marco))->values)
        ->keyBy(fn (CardValueData $value): string => $value->label);

    expect($values['Type']->text)->toBe('Car')
        ->and($values['Policyholder']->text)->toBe('Marco Bianchi')
        ->and($values['Policyholder']->href)->toBe(route('people.show', $this->policy->person_id))
        // The start is a tile above the data, so it is not written twice
        ->and($values->has('Start'))->toBeFalse()
        ->and($values->has('Policy number'))->toBeFalse();
});

test('it names the supply point code of a gas utility PDR', function (): void {
    $gas = insideBianchi(fn (): Utility => Utility::query()->where('type', 'gas')->sole());
    insideBianchi(fn () => $gas->update(['supply_point_code' => 'IT001E00000001']));
    $labels = array_map(
        fn (CardValueData $value): string => $value->label,
        insideBianchi(fn () => $this->details->of($gas, $this->marco))->values,
    );

    expect($labels)->toContain('PDR code')->not->toContain('POD code');
});

test('it lists the linked cards both ways', function (): void {
    $names = fn ($card): array => array_map(
        fn (CardData $row): string => $row->label,
        insideBianchi(fn () => $this->details->of($card, $this->marco))->related,
    );
    $home = insideBianchi(fn (): Home => Home::query()->where('name', 'Casa di via Roma')->sole());

    expect($names($this->panda))->toContain('RC auto Panda')
        ->and($names($this->policy))->toContain('Fiat Panda')
        ->and($names($home))->toContain('Electricity - Edison', 'Gas - Enel Energia', 'Water - Hera', 'TV Samsung');
});

test('it lists the documents of the card', function (): void {
    $titles = array_map(
        fn (DocumentData $document): string => $document->title,
        insideBianchi(fn () => $this->details->of($this->panda, $this->marco))->documents,
    );

    expect($titles)->toContain('Polizza RC auto 2026');
});

test('it prepares the form with presets', function (): void {
    $form = insideBianchi(fn () => $this->details->form(CardTypes::find('insurance_policy'), null, [
        'vehicle_id' => (string) $this->panda->id,
        'family_id' => '99',
    ]));

    expect($form['values']['vehicle_id'])->toBe((string) $this->panda->id)
        ->and($form['values'])->not->toHaveKey('family_id')
        ->and($form['type']->type)->toBe('insurance_policy')
        ->and(collect($form['fields'])->firstWhere('column', 'vehicle_id')->show_when)->toBe(['type' => ['car']]);
});

test('it sends the ids of a saved card as text, like the options of their menus', function (): void {
    $policy = insideBianchi(fn (): InsurancePolicy => InsurancePolicy::query()->where('vehicle_id', $this->panda->id)->firstOrFail());
    $form = insideBianchi(fn () => $this->details->form(CardTypes::find('insurance_policy'), $policy, []));
    $vehicle = collect($form['fields'])->firstWhere('column', 'vehicle_id');

    expect($form['values']['vehicle_id'])->toBe((string) $this->panda->id)
        ->and(collect($vehicle->options)->pluck('value')->all())->toContain($form['values']['vehicle_id'])
        ->and($form['values']['organization_id'])->toBeString();
});

test('it lets only an admin edit and delete', function (): void {
    $rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);

    $asMarco = insideBianchi(fn () => $this->details->of($this->panda, $this->marco));
    $asRosa = insideBianchi(fn () => $this->details->of($this->panda, $rosa));

    expect($asMarco->can_edit)->toBeTrue()->and($asMarco->can_delete)->toBeTrue()
        ->and($asRosa->can_edit)->toBeFalse()->and($asRosa->can_delete)->toBeFalse();
});

test('a past expiry of a closed card is no longer a deadline in its tiles, unless the card expired by itself', function (string $status, bool $expires): void {
    insideBianchi(fn () => $this->policy->update(['expiry_date' => now()->subDays(36)->toDateString(), 'status' => $status]));

    $expiry = collect(insideBianchi(fn () => $this->details->of($this->policy->refresh(), $this->marco))->dates)
        ->firstWhere('label', 'Expiry');

    expect($expiry->expires)->toBe($expires);
})->with([
    'cancelled' => ['cancelled', false],
    // An active policy past its date turns expired when saved: the renewal is still to do
    'active' => ['active', true],
    'expired' => ['expired', true],
]);

test('it shows the status as a figure, coloured by where it stands', function (string $status, string $expiry, string $text, string $tone): void {
    insideBianchi(fn () => $this->policy->update(['expiry_date' => $expiry, 'status' => $status]));

    $figure = insideBianchi(fn () => $this->details->of($this->policy->refresh(), $this->marco))->status;

    expect($figure->label)->toBe('Status')
        ->and($figure->text)->toBe($text)
        ->and($figure->tone)->toBe($tone)
        ->and($figure->amount)->toBeNull();
})->with([
    // Fixed dates around the travelled day (26/10/2026): a dataset is built before beforeEach travels
    'active' => ['active', '2026-11-26', 'Active', 'open'],
    'cancelled' => ['cancelled', '2026-11-26', 'Cancelled', 'closed'],
    // An active policy past its date turns expired when saved: the renewal is still to do
    'expired by itself' => ['active', '2026-09-26', 'Expired', 'overdue'],
]);

test('the dates of the tiles and the status leave the data', function (): void {
    insideBianchi(fn () => $this->policy->update(['start_date' => '2025-11-21', 'expiry_date' => now()->addMonth()->toDateString(), 'status' => 'active']));

    $labels = collect(insideBianchi(fn () => $this->details->of($this->policy->refresh(), $this->marco))->values)->pluck('label');

    expect($labels)->not->toContain('Start')
        ->and($labels)->not->toContain('Expiry')
        ->and($labels)->not->toContain('Status')
        ->and($labels)->toContain('Title');
});

test('a card without a status has no status figure', function (): void {
    $checkup = insideBianchi(fn (): HealthEvent => HealthEvent::query()->firstOrFail());

    expect(insideBianchi(fn () => $this->details->of($checkup, $this->marco))->status)->toBeNull();
});

test('the tiles of a policy start with the start', function (): void {
    insideBianchi(fn () => $this->policy->update(['start_date' => '2025-11-21', 'expiry_date' => now()->addMonth()->toDateString()]));

    $dates = insideBianchi(fn () => $this->details->of($this->policy->refresh(), $this->marco))->dates;

    expect($dates[0]->label)->toBe('Start')
        ->and($dates[1]->label)->toBe('Expiry');
});

test('a paid off loan is closed for good, not red, though it reaches that status by itself', function (): void {
    $loan = insideBianchi(fn (): Loan => Loan::query()->firstOrFail());
    insideBianchi(fn () => $loan->update(['status' => 'paid_off']));

    $figure = insideBianchi(fn () => $this->details->of($loan->refresh(), $this->marco))->status;

    expect($figure->tone)->toBe('closed')
        ->and($figure->label)->toBe('Status');
});

test('the card of an electricity utility has its last 12 months of consumption and the link to the page', function (): void {
    $luce = insideBianchi(fn (): Utility => Utility::query()->where('type', 'electricity')->sole());
    $consumption = insideBianchi(fn () => $this->details->of($luce, $this->marco))->consumption;

    expect($consumption)->not->toBeNull()
        ->and($consumption['quantity'])->toBe(2711.0)
        ->and($consumption['unit'])->toBe('kwh')
        ->and(round((float) $consumption['price'], 2))->toBe(0.31)
        ->and($consumption['href'])->toBe(route('budget.consumption', ['utility' => $luce->id], false));
});

test('a utility without consumption, or without a unit, has no consumption on its card', function (): void {
    $internet = insideBianchi(fn (): Utility => Utility::factory()->create(['family_id' => $this->marco->family_id, 'type' => UtilityType::Internet]));
    $empty = insideBianchi(fn (): Utility => Utility::factory()->create(['family_id' => $this->marco->family_id, 'type' => UtilityType::Electricity]));

    expect(insideBianchi(fn () => $this->details->of($internet, $this->marco))->consumption)->toBeNull()
        ->and(insideBianchi(fn () => $this->details->of($empty, $this->marco))->consumption)->toBeNull()
        ->and(insideBianchi(fn () => $this->details->of($this->policy, $this->marco))->consumption)->toBeNull();
});

test('it shows five open linked cards, the soonest first, and counts all of them', function (): void {
    $c4 = linkedCardsVehicle();
    $detail = insideBianchi(fn () => $this->details->of($c4, $this->marco));

    expect(array_map(fn (CardData $row): string => $row->label, $detail->related))
        ->toBe(['Active policy', 'Service 2025', 'Service 2024', 'Service 2023', 'Service 2022'])
        ->and($detail->related_more)->toBe(['href' => route('cards.related', ['type' => 'vehicle', 'id' => $c4->id]), 'count' => 7]);
});

test('it puts the closed linked cards last in the whole list', function (): void {
    $c4 = linkedCardsVehicle();
    $labels = array_map(fn (CardData $row): string => $row->label, insideBianchi(fn (): array => $this->details->related(CardTypes::for($c4), $c4)));

    expect($labels)->toHaveCount(7)->and($labels[6])->toBe('Old policy')->and($labels[0])->toBe('Active policy')->and($labels[1])->toBe('Service 2025');
});

test('it has no link to all the linked cards when they all fit and none is closed', function (): void {
    $c4 = linkedCardsVehicle('Panda bis', [2024, 2025], expiredPolicy: false);

    expect(insideBianchi(fn () => $this->details->of($c4, $this->marco))->related_more)->toBeNull();
});

test('it links to all the linked cards when one is closed', function (): void {
    $c4 = linkedCardsVehicle('Panda bis', [], expiredPolicy: true);
    $detail = insideBianchi(fn () => $this->details->of($c4, $this->marco));

    expect($detail->related)->toHaveCount(1)->and($detail->related_more['count'])->toBe(2);
});

/**
 * The deadlines of the Panda of the Bianchi as rows of label, date, detail, amount and estimate.
 *
 * @param  list<CardDeadlineData>|null  $deadlines  the deadlines of the card
 * @return list<array{0: string, 1: ?string, 2: ?string, 3: ?float, 4: bool}>
 */
function cardDetailsDeadlineRows(?array $deadlines): array
{
    return array_map(fn (CardDeadlineData $row): array => [$row->label, $row->date, $row->detail, $row->amount, $row->estimated], $deadlines ?? []);
}

test('a vehicle lists its deadlines by date, with the planned expenses of its policies', function (): void {
    $detail = insideBianchi(fn () => $this->details->of($this->panda, $this->marco));
    $fuel = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Carburante')->sole());

    expect(cardDetailsDeadlineRows($detail->deadlines))->toBe([
        ['Carburante', '2026-11-01', 'every month', 80.0, true],
        ['Assicurazione auto', '2026-11-21', 'RC auto Panda', 418.0, false],
        ['Inspection', '2027-01-09', null, null, false],
        ['Road tax', '2027-01-31', null, 198.0, true],
    ])
        ->and($detail->deadlines[0]->href)->toBe(route('budget.planned.show', ['kind' => 'expense', 'id' => $fuel->id]))
        ->and($detail->deadlines[0]->add_expense_href)->toBeNull()
        ->and($detail->deadlines[2]->href)->toBeNull()
        ->and($detail->deadlines[2]->add_expense_href)->toBe(route('budget.planned.create', ['kind' => 'expense', 'card' => 'vehicle:'.$this->panda->id, 'deadline' => 'vehicles.inspection_due_date']))
        ->and($detail->deadlines[3]->add_expense_href)->toBeNull();
});

test('a vehicle has no tiles of dates: its status is the first line of its data', function (): void {
    $detail = insideBianchi(fn () => $this->details->of($this->panda, $this->marco));

    expect($detail->dates)->toBe([])
        ->and($detail->status)->toBeNull()
        ->and($detail->values[0]->label)->toBe('Status')
        ->and($detail->values[0]->text)->toBe('Active')
        ->and($detail->values[0]->tone)->toBe('open')
        ->and(collect($detail->values)->pluck('label')->all())->not->toContain('Road tax')
        ->and($detail->budget?->next)->toBeNull()
        ->and($detail->budget?->numbers)->toBe([]);
});

test('a vehicle without the date of its road tax asks for it at the bottom', function (): void {
    insideBianchi(fn () => $this->panda->update(['road_tax_due_date' => null]));
    $detail = insideBianchi(fn () => $this->details->of($this->panda->refresh(), $this->marco));
    $last = $detail->deadlines[array_key_last($detail->deadlines)];

    expect(array_column(cardDetailsDeadlineRows($detail->deadlines), 0))->toBe(['Carburante', 'Assicurazione auto', 'Inspection', 'Road tax'])
        ->and($last->date)->toBeNull()
        ->and($last->amount)->toBeNull()
        ->and($last->add_date_href)->toBe(route('cards.edit', ['type' => 'vehicle', 'id' => $this->panda->id]));
});

test('a date paid by a planned expense with no due date open in the next months keeps its row, from the card', function (): void {
    // Like an inspection every two years just paid: the next due date of the road tax falls after the 24 months read
    $tax = insideBianchi(fn (): PlannedExpense => PlannedExpense::query()->where('title', 'Bollo auto')->sole());
    PlannedExpense::query()->withoutGlobalScopes()->whereKey($tax->id)->update(['start_date' => '2029-01-31']);
    $detail = insideBianchi(fn () => $this->details->of($this->panda->refresh(), $this->marco));
    $row = collect($detail->deadlines)->firstWhere('label', 'Road tax');

    expect($row)->toBeInstanceOf(CardDeadlineData::class)
        ->and(cardDetailsDeadlineRows([$row]))->toBe([['Road tax', '2027-01-31', null, null, false]])
        ->and($row->add_expense_href)->toBeNull()
        ->and($row->href)->toBe(route('budget.planned.show', ['kind' => 'expense', 'id' => $tax->id]));
});

test('a member sees the deadlines without the links to add what is missing', function (): void {
    $rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);
    insideBianchi(fn () => $this->panda->update(['road_tax_due_date' => null]));
    $deadlines = insideBianchi(fn () => $this->details->of($this->panda->refresh(), $rosa))->deadlines;

    expect($deadlines)->toHaveCount(4)
        ->and(collect($deadlines)->pluck('add_expense_href')->filter()->all())->toBe([])
        ->and(collect($deadlines)->pluck('add_date_href')->filter()->all())->toBe([]);
});

test('a sold vehicle asks for nothing', function (): void {
    insideBianchi(fn () => $this->panda->update(['status' => 'sold', 'road_tax_due_date' => null]));
    $deadlines = insideBianchi(fn () => $this->details->of($this->panda->refresh(), $this->marco))->deadlines;

    // The planned expense of the road tax still runs, so its row stays; the dates of the card no longer count
    expect(collect($deadlines)->pluck('label')->all())->toBe(['Carburante', 'Assicurazione auto', 'Road tax'])
        ->and(collect($deadlines)->pluck('date')->all())->not->toContain(null)
        ->and(collect($deadlines)->filter(fn (CardDeadlineData $row): bool => $row->add_expense_href !== null || $row->add_date_href !== null)->all())->toBe([]);
});

test('a policy keeps its tiles, its status and its next due date', function (): void {
    $detail = insideBianchi(fn () => $this->details->of($this->policy, $this->marco));

    expect($detail->deadlines)->toBeNull()
        ->and($detail->dates)->not->toBe([])
        ->and($detail->status?->text)->toBe('Active')
        ->and($detail->budget?->next?->date)->toBe('2026-11-21')
        ->and($detail->budget?->summary)->toBeNull();
});
