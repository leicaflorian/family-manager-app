<?php

declare(strict_types=1);

use App\Enums\HealthEventType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Expense;
use App\Models\File;
use App\Models\HealthEvent;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
    Date::setTestNow(CarbonImmutable::parse('2026-10-25 19:04', 'Europe/Rome'));
    $this->marco = registerFamily();
    $this->laura = User::factory()->for($this->marco->family)->create(['role' => Role::Member, 'email_verified_at' => now()]);
    $this->giulia = Person::factory()->for($this->marco->family)->create(['first_name' => 'Giulia']);
});

afterEach(function (): void {
    Date::setTestNow();
});

test('a member imports the Studio Ferri invoice', function (): void {
    $file = File::factory()->for($this->marco->family)->read([
        'kind' => 'invoice', 'title' => 'Visita dal dentista', 'issuer_name' => 'Studio Ferri', 'organization_type' => OrganizationType::Healthcare->value,
        'total_amount' => 120, 'issue_date' => '2026-10-22', 'person_id' => $this->giulia->id, 'health_type' => HealthEventType::Visit->value,
        'next_checkup_date' => '2027-04-22',
    ])->create(['to_sort' => true, 'created_by' => $this->marco->id]);

    $this->actingAs($this->laura)->post(route('import.store'), ['files' => [[
        'file_id' => $file->id,
        'document' => ['title' => 'Fattura Studio Ferri', 'status' => 'active', 'person_id' => $this->giulia->id, 'date' => '2026-10-22'],
        'also' => ['movement' => true, 'due' => false, 'event' => true, 'records' => ['organization']],
    ]]])->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));

    $ferri = Organization::query()->where('name', 'Studio Ferri')->sole();
    $document = Document::query()->sole();
    expect($document->organization_id)->toBe($ferri->id)
        ->and(Expense::query()->sole()->organization_id)->toBe($ferri->id)
        ->and((float) Expense::query()->sole()->amount)->toBe(120.0)
        ->and(HealthEvent::query()->sole()->organization_id)->toBe($ferri->id)
        ->and(HealthEvent::query()->sole()->person_id)->toBe($this->giulia->id);
});

test('outside the import a member still cannot create an organization', function (): void {
    $ferri = Organization::factory()->for($this->marco->family)->create(['name' => 'Studio Ferri']);

    $this->actingAs($this->laura)->post(route('organizations.store'), ['name' => 'Studio Rossi'])->assertForbidden();
    $this->actingAs($this->laura)->delete(route('organizations.destroy', $ferri))->assertForbidden();

    expect(Organization::query()->pluck('name')->all())->toBe(['Studio Ferri']);
});
