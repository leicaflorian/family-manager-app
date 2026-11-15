<?php

declare(strict_types=1);

use App\Data\CardChoiceData;
use App\Enums\InsurancePolicyStatus;
use App\Enums\VehicleStatus;
use App\Models\ExpenseCategory;
use App\Models\InsurancePolicy;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\BudgetFormOptions;
use Database\Seeders\BianchiFamilySeeder;

beforeEach(function (): void {
    $this->seed(BianchiFamilySeeder::class);
});

it('lists the cards of every type, the closed ones last', function (): void {
    [$cards, $panda, $old, $bike] = insideBianchi(function (): array {
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        $old = Vehicle::factory()->create(['name' => 'Alfa 147', 'status' => VehicleStatus::Sold, 'family_id' => $panda->family_id]);
        $bike = Vehicle::factory()->create(['name' => 'Bici', 'status' => VehicleStatus::Active, 'family_id' => $panda->family_id]);

        return [BudgetFormOptions::for('expense')['cards'], $panda, $old, $bike];
    });
    $values = collect($cards)->map(fn (CardChoiceData $card): string => $card->value);
    $types = $values->map(fn (string $value): string => explode(':', $value)[0])->unique()->values();

    expect($types->all())->toContain('belonging', 'health_event', 'financial_account', 'employment')
        ->and($values->search("vehicle:{$bike->id}"))->toBeLessThan($values->search("vehicle:{$panda->id}"))
        ->and($values->search("vehicle:{$panda->id}"))->toBeLessThan($values->search("vehicle:{$old->id}"))
        ->and(collect($cards)->firstWhere('value', "vehicle:{$panda->id}")?->label)->toBe('Vehicle · Fiat Panda');
});

it('tells a closed card by its status, and an expired one by its expiry', function (): void {
    [$cards, $old, $current, $sold, $cancelled] = insideBianchi(function (): array {
        $familyId = Vehicle::query()->where('name', 'Fiat Panda')->sole()->family_id;
        $policy = ['title' => 'RCA Twingo', 'family_id' => $familyId];

        $created = [
            InsurancePolicy::factory()->create([...$policy, 'status' => InsurancePolicyStatus::Expired, 'expiry_date' => '2026-03-14']),
            InsurancePolicy::factory()->create([...$policy, 'status' => InsurancePolicyStatus::Active, 'expiry_date' => '2027-03-14']),
            Vehicle::factory()->create(['name' => 'Alfa 147', 'status' => VehicleStatus::Sold, 'family_id' => $familyId, 'inspection_due_date' => '2025-01-10']),
            InsurancePolicy::factory()->create([...$policy, 'status' => InsurancePolicyStatus::Cancelled, 'expiry_date' => '2026-05-01']),
        ];

        return [BudgetFormOptions::for('expense')['cards'], ...$created];
    });
    $label = fn (string $value): ?string => collect($cards)->firstWhere('value', $value)?->label;

    expect($label("insurance_policy:{$old->id}"))->toBe('Policy · RCA Twingo — expired on 14/03/2026')
        ->and($label("insurance_policy:{$current->id}"))->toBe('Policy · RCA Twingo')
        ->and($label("vehicle:{$sold->id}"))->toBe('Vehicle · Alfa 147 — sold')
        ->and($label("insurance_policy:{$cancelled->id}"))->toBe('Policy · RCA Twingo — cancelled');
});

it('tells an expired card in Italian', function (): void {
    App::setLocale('it');

    [$cards, $old] = insideBianchi(function (): array {
        $familyId = Vehicle::query()->where('name', 'Fiat Panda')->sole()->family_id;
        $old = InsurancePolicy::factory()->create(['title' => 'RCA Twingo', 'family_id' => $familyId, 'status' => InsurancePolicyStatus::Expired, 'expiry_date' => '2026-03-14']);

        return [BudgetFormOptions::for('expense')['cards'], $old];
    });

    expect(collect($cards)->firstWhere('value', "insurance_policy:{$old->id}")?->label)->toBe('Polizza · RCA Twingo — scaduta il 14/03/2026');
});

it('tells the card type of each category', function (): void {
    [$options, $expense, $parking] = insideBianchi(function (): array {
        $car = ExpenseCategory::query()->where('name', 'Auto')->sole();
        $parking = ExpenseCategory::query()->create(['name' => 'Parcheggi', 'parent_id' => $car->id, 'sort' => 1]);

        return [BudgetFormOptions::forMovement(), BudgetFormOptions::for('expense'), $parking];
    });

    expect($options['category_card_types'][(string) $parking->id])->toBe('vehicle')
        ->and($expense['category_card_types'])->toBe($options['category_card_types']);
});

it('labels the card types', function (): void {
    $options = insideBianchi(fn (): array => BudgetFormOptions::forMovement());

    expect($options['card_type_labels']['vehicle'])->toBe('Vehicle')
        ->and($options['card_type_labels'])->toHaveCount(13)
        ->and($options['cards'])->not->toBeEmpty();
});

it('tells the holder, the organization and the category of each card', function (): void {
    [$cards, $panda, $car, $edison] = insideBianchi(fn (): array => [
        BudgetFormOptions::forMovement()['cards'],
        Vehicle::query()->where('name', 'Fiat Panda')->sole(),
        ExpenseCategory::query()->where('name', 'Auto')->sole(),
        Utility::query()->whereNotNull('organization_id')->firstOrFail(),
    ]);
    $choice = fn (string $value): ?CardChoiceData => collect($cards)->firstWhere('value', $value);

    expect($panda->person_id)->not->toBeNull()
        ->and($choice("vehicle:{$panda->id}")?->person_id)->toBe((string) $panda->person_id)
        ->and($choice("vehicle:{$panda->id}")?->organization_id)->toBeNull()
        ->and($choice("vehicle:{$panda->id}")?->category_id)->toBe((string) $car->id)
        ->and($choice("utility:{$edison->id}")?->organization_id)->toBe((string) $edison->organization_id);
});
