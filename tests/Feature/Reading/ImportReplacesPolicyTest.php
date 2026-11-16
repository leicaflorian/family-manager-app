<?php

declare(strict_types=1);

use App\Enums\InsurancePolicyStatus;
use App\Enums\IntervalUnit;
use App\Enums\Role;
use App\Models\File;
use App\Models\InsurancePolicy;
use App\Models\Organization;
use App\Models\PlannedExpense;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Queue::fake();
    Date::setTestNow(CarbonImmutable::parse('2026-11-16 10:00', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->familyId = $this->marco->family_id;
    $this->laura = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);

    CurrentFamily::using($this->familyId, function (): void {
        $this->allianz = Organization::factory()->create(['family_id' => $this->familyId, 'name' => 'Allianz']);
        $this->panda = Vehicle::factory()->create(['family_id' => $this->familyId, 'name' => 'Panda', 'plate_number' => 'GH 482 TK']);
        $this->old = InsurancePolicy::factory()->create([
            'family_id' => $this->familyId, 'title' => 'RC auto Panda', 'policy_number' => '40/123456',
            'vehicle_id' => $this->panda->id, 'expiry_date' => '2026-11-21',
        ]);
        $this->premium = PlannedExpense::factory()->forCard($this->old)->create([
            'family_id' => $this->familyId, 'title' => 'Premio RC auto', 'amount' => 480, 'interval' => 1, 'interval_unit' => IntervalUnit::Year,
        ]);
    });

    $this->paper = File::factory()->for($this->marco->family)->read([
        'kind' => 'insurance', 'title' => 'Polizza RC auto', 'organization_id' => $this->allianz->id, 'policy_number' => '40/987654',
        'plate_number' => 'GH 482 TK', 'insurance_type' => 'car', 'issue_date' => '2026-11-15',
        'period_start' => '2026-11-21', 'expiry_date' => '2027-11-21', 'total_amount' => 420, 'due_date' => '2026-11-21',
    ])->create(['to_sort' => true]);
});

afterEach(function (): void {
    Date::setTestNow();
});

/**
 * The body of `POST import` for one file, as the page sends it: every field of the document, the links of the form.
 *
 * @param  array<string, mixed>  $also
 * @return array{files: list<array<string, mixed>>}
 */
function replacingImport(File $file, array $also): array
{
    return ['files' => [[
        'file_id' => $file->id,
        'document' => [
            'title' => 'Polizza RC auto', 'category_id' => null, 'person_id' => null, 'organization_id' => test()->allianz->id,
            'date' => '2026-11-15', 'expiry_date' => null, 'status' => 'active', 'tags' => [], 'notes' => null, 'links' => [],
        ],
        'also' => ['movement' => false, 'event' => false, 'records' => ['policy'], 'targets' => ['movement' => 'auto', 'event' => 'auto', 'records' => []], 'policy' => 'auto', 'card_type' => null, ...$also],
    ]]];
}

/**
 * A model read again inside the family of the test.
 *
 * @template T of Model
 *
 * @param  T  $model
 * @return T
 */
function freshInFamily(object $test, Model $model): Model
{
    return CurrentFamily::using($test->familyId, fn (): Model => $model->fresh());
}

it('proposes to cancel the old policy of the same vehicle', function (): void {
    $this->actingAs($this->marco)->get(route('import', ['files' => (string) $this->paper->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where("proposals.{$this->paper->id}.records", function (Collection $records): bool {
                $policy = $records->firstWhere('kind', 'policy');

                return $policy !== null && $policy['existing'] === null
                    && array_column($policy['replaces'], 'id') === [$this->old->id]
                    && $policy['replaces'][0]['label'] === 'RC auto Panda · 40/123456'
                    && $policy['replaces'][0]['expiry_date'] === '2026-11-21';
            }));
});

it('cancels the old policy when the box is ticked', function (): void {
    $this->actingAs($this->marco)->post(route('import.store'), replacingImport($this->paper, ['replace_policies' => [$this->old->id]]))
        ->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

    $new = CurrentFamily::using($this->familyId, fn (): InsurancePolicy => InsurancePolicy::query()->where('policy_number', '40/987654')->sole());
    $premium = freshInFamily($this, $this->premium);
    expect(freshInFamily($this, $this->old)->status)->toBe(InsurancePolicyStatus::Cancelled)
        ->and($new->status)->toBe(InsurancePolicyStatus::Active)
        ->and($premium->card_id)->toBe($new->id)
        ->and($premium->end_date)->toBeNull();
});

it('ends the old premium when the import makes a new one', function (): void {
    $this->actingAs($this->marco)->post(route('import.store'), replacingImport($this->paper, ['movement' => true, 'due' => true, 'replace_policies' => [$this->old->id]]))
        ->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

    $new = CurrentFamily::using($this->familyId, fn (): InsurancePolicy => InsurancePolicy::query()->where('policy_number', '40/987654')->sole());
    $own = CurrentFamily::using($this->familyId, fn (): int => PlannedExpense::query()->where('card_type', 'insurance_policy')->where('card_id', $new->id)->count());
    $premium = freshInFamily($this, $this->premium);
    expect(freshInFamily($this, $this->old)->status)->toBe(InsurancePolicyStatus::Cancelled)
        ->and($own)->toBe(1)
        ->and($premium->card_id)->toBe($this->old->id)
        ->and($premium->end_date?->toDateString())->toBe('2026-11-21');
});

it('cancels nothing for a member', function (): void {
    $this->actingAs($this->laura)->get(route('import', ['files' => (string) $this->paper->id]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where("proposals.{$this->paper->id}.records", fn (Collection $records): bool => $records->firstWhere('kind', 'policy')['replaces'] === []));

    $this->actingAs($this->laura)->post(route('import.store'), replacingImport($this->paper, ['replace_policies' => [$this->old->id]]))
        ->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

    expect(freshInFamily($this, $this->old)->status)->toBe(InsurancePolicyStatus::Active)
        ->and(freshInFamily($this, $this->premium)->card_id)->toBe($this->old->id);
});

it('ignores an id not proposed', function (): void {
    $other = CurrentFamily::using($this->familyId, function (): InsurancePolicy {
        $tipo = Vehicle::factory()->create(['family_id' => $this->familyId, 'name' => 'Tipo', 'plate_number' => 'AB 123 CD']);

        return InsurancePolicy::factory()->create(['family_id' => $this->familyId, 'title' => 'RC auto Tipo', 'vehicle_id' => $tipo->id]);
    });

    $this->actingAs($this->marco)->post(route('import.store'), replacingImport($this->paper, ['replace_policies' => [$other->id]]))
        ->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

    expect(freshInFamily($this, $other)->status)->toBe(InsurancePolicyStatus::Active)
        ->and(freshInFamily($this, $this->old)->status)->toBe(InsurancePolicyStatus::Active);
});
