<?php

declare(strict_types=1);

use App\Cards\CardTypes;
use App\Data\CardData;
use App\Data\CardValueData;
use App\Data\DocumentData;
use App\Enums\Role;
use App\Models\Family;
use App\Models\Home;
use App\Models\InsurancePolicy;
use App\Models\User;
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

    expect($form['values']['vehicle_id'])->toBe($this->panda->id)
        ->and($form['values'])->not->toHaveKey('family_id')
        ->and($form['type']->type)->toBe('insurance_policy')
        ->and(collect($form['fields'])->firstWhere('column', 'vehicle_id')->show_when)->toBe(['type' => ['car']]);
});

test('it lets only an admin edit and delete', function (): void {
    $rosa = User::factory()->for(Family::query()->where('name', 'Bianchi')->sole())->create(['role' => Role::Member]);

    $asMarco = insideBianchi(fn () => $this->details->of($this->panda, $this->marco));
    $asRosa = insideBianchi(fn () => $this->details->of($this->panda, $rosa));

    expect($asMarco->can_edit)->toBeTrue()->and($asMarco->can_delete)->toBeTrue()
        ->and($asRosa->can_edit)->toBeFalse()->and($asRosa->can_delete)->toBeFalse();
});
