<?php

declare(strict_types=1);

use App\Cards\CardTypes;
use App\Data\CardData;
use App\Data\CardLinkData;
use App\Data\CardValueData;
use App\Data\DocumentData;
use App\Enums\Role;
use App\Models\Family;
use App\Models\HealthEvent;
use App\Models\Home;
use App\Models\InsurancePolicy;
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
        ->and($values['Start']->text)->toBe('20/11/2025')
        ->and($values->has('Policy number'))->toBeFalse();
});

test('it links the organization and the person of the heading', function (): void {
    $visit = insideBianchi(fn (): HealthEvent => HealthEvent::query()->where('title', 'Dentista di Laura')->sole());
    $heading = insideBianchi(fn () => $this->details->of($visit, $this->marco))->heading;

    expect(array_map(fn (CardLinkData $part): array => [$part->text, $part->href], $heading))->toBe([
        ['Visit', null],
        ['Studio Ferri', route('organizations.show', $visit->organization_id)],
        ['person Laura', route('people.show', $visit->person_id)],
    ]);
});

test('it leaves unlinked a subtitle that names no organization', function (): void {
    $heading = insideBianchi(fn () => $this->details->of($this->policy, $this->marco))->heading;

    expect(array_map(fn (CardLinkData $part): ?string => $part->href, $heading))->toBe([
        null,
        route('people.show', $this->policy->person_id),
    ]);
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
