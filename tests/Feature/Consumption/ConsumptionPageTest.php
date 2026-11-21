<?php

declare(strict_types=1);

use App\Contracts\DocumentReader;
use App\Enums\ConsumptionOutcome;
use App\Enums\ReadingPurpose;
use App\Enums\Role;
use App\Enums\UtilityStatus;
use App\Enums\UtilityType;
use App\Models\AiReading;
use App\Models\Document;
use App\Models\Family;
use App\Models\File;
use App\Models\User;
use App\Models\Utility;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeDocumentReader;

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-11-21 10:00', 'Europe/Rome'));
    App::setLocale('it');
    $this->withHeader('Accept-Language', 'it');
    $this->seed(BianchiFamilySeeder::class);
    $this->marco = User::query()->where('email', 'marco.bianchi@example.com')->sole();
});

afterEach(fn () => CurrentFamily::clear());

/** A utility of the Bianchi, by its type, read inside their family. */
function consumptionPageUtility(UtilityType $type): Utility
{
    return CurrentFamily::using(test()->marco->family_id, static fn (): Utility => Utility::query()->where('type', $type)->orderBy('id')->firstOrFail());
}

/**
 * A document of the Bianchi linked to a utility, with a file of the given type.
 *
 * @param  string  $mime  the type of its file
 */
function consumptionPageBill(Utility $utility, string $title, string $mime = 'application/pdf'): Document
{
    $familyId = test()->marco->family_id;

    return CurrentFamily::using($familyId, static function () use ($utility, $title, $mime, $familyId): Document {
        $file = File::factory()->create(['family_id' => $familyId, 'mime_type' => $mime]);
        $document = Document::factory()->create(['family_id' => $familyId, 'file_id' => $file->id, 'title' => $title, 'date' => '2025-03-10']);
        $utility->documents()->attach($document->id);

        return $document;
    });
}

/** A consumption reading of a document, with its outcome. */
function consumptionPageReading(Document $document, ?ConsumptionOutcome $outcome, string $at = '2026-11-20 10:00'): void
{
    AiReading::factory()->create(['family_id' => test()->marco->family_id, 'file_id' => $document->file_id, 'purpose' => ReadingPurpose::Consumption, 'outcome' => $outcome, 'created_at' => $at]);
}

it('opens the electricity of the last 12 months with data when nothing is asked', function (): void {
    $this->actingAs($this->marco)->get('/budget/consumption')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('budget/Consumption', false)
            ->where('consumption.utility.title', 'Luce - Edison')
            ->where('consumption.utility.type', 'electricity')
            ->where('consumption.utility.unit', 'kwh')
            ->where('consumption.utility.href', route('cards.show', ['type' => 'utility', 'id' => consumptionPageUtility(UtilityType::Electricity)->id]))
            ->where('consumption.from', '2025-10')
            ->where('consumption.to', '2026-09')
            ->where('consumption.previous', '2025-09')
            ->where('consumption.next', null)
            ->where('consumption.month', null)
            ->has('consumption.months', 12)
            ->where('consumption.figures.quantity', 2711)
            ->where('consumption.types', [['type' => 'electricity', 'label' => 'Luce'], ['type' => 'gas', 'label' => 'Gas'], ['type' => 'water', 'label' => 'Acqua']])
            ->where('consumption.utilities', [['id' => consumptionPageUtility(UtilityType::Electricity)->id, 'title' => 'Luce - Edison', 'subtitle' => 'Casa di via Roma']])
            ->has('consumption.bills', 12)
            ->where('consumption.bills.0.period_start', '2026-09-01')
            // The October bill and the contract of the seeder have no row and were never read for it
            ->where('consumption.archive.count', 2)
            ->where('consumption.toCheck', [])
            ->where('consumption.canCreate', true));
});

it('opens for a member too', function (): void {
    $rosa = User::factory()->create(['role' => Role::Member, 'family_id' => $this->marco->family_id]);

    $this->actingAs($rosa)->get('/budget/consumption')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('consumption.utility.title', 'Luce - Edison')
            ->where('consumption.canCreate', true)
            ->where('consumption.bills.0.can_update', true)
            ->where('consumption.bills.0.can_delete', false));
});

it('answers 404 to a utility of another family, of another type or that does not exist', function (): void {
    $other = Family::factory()->create();
    $foreign = CurrentFamily::using($other->id, static fn (): Utility => Utility::factory()->create(['family_id' => $other->id, 'type' => UtilityType::Electricity]));
    $internet = CurrentFamily::using($this->marco->family_id, fn (): Utility => Utility::factory()->create(['family_id' => $this->marco->family_id, 'type' => UtilityType::Internet]));

    $this->actingAs($this->marco)->get('/budget/consumption?utility='.$foreign->id)->assertNotFound();
    $this->actingAs($this->marco)->get('/budget/consumption?utility='.$internet->id)->assertNotFound();
    $this->actingAs($this->marco)->get('/budget/consumption?utility=999999')->assertNotFound();
    $this->actingAs($this->marco)->get('/budget/consumption?utility=x')->assertNotFound();
});

it('answers 404 to a month it cannot read', function (string $query): void {
    $this->actingAs($this->marco)->get('/budget/consumption?'.$query)->assertNotFound();
})->with([
    'a month 13' => 'to=2026-13',
    'a month that is not one' => 'month=x',
    'a month beyond the current one' => 'to=2026-12',
    'a month before the first 12 months with data' => 'to=2025-08',
    'a month outside the 12 shown' => 'month=2025-09',
]);

it('goes back one year and forward again', function (): void {
    $this->actingAs($this->marco)->get('/budget/consumption?to=2025-09')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('consumption.from', '2024-10')
            ->where('consumption.to', '2025-09')
            ->where('consumption.months.0.month', '2024-10')
            ->where('consumption.previous', null)
            ->where('consumption.next', '2026-09'));
});

it('opens a month of the 12 shown', function (): void {
    $this->actingAs($this->marco)->get('/budget/consumption?month=2026-01')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('consumption.month', '2026-01'));
});

it('opens the asked utility, with the others of its type', function (): void {
    $gas = consumptionPageUtility(UtilityType::Gas);
    $second = CurrentFamily::using($this->marco->family_id, fn (): Utility => Utility::factory()->create(['family_id' => $this->marco->family_id, 'type' => UtilityType::Gas, 'status' => UtilityStatus::Terminated, 'home_id' => null]));

    $this->actingAs($this->marco)->get('/budget/consumption?utility='.$gas->id)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('consumption.utility.id', $gas->id)
            ->where('consumption.utility.unit', 'smc')
            ->has('consumption.utilities', 2)
            ->where('consumption.utilities.0.id', $gas->id)
            ->where('consumption.utilities.1.id', $second->id)
            ->where('consumption.utilities.1.subtitle', ''));
});

it('answers the current month for a utility without rows', function (): void {
    $water = consumptionPageUtility(UtilityType::Water);
    CurrentFamily::using($this->marco->family_id, fn () => $water->consumptions()->delete());

    $this->actingAs($this->marco)->get('/budget/consumption?utility='.$water->id)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('consumption.to', '2026-11')
            ->where('consumption.previous', null)
            ->where('consumption.next', null)
            ->has('consumption.bills', 0)
            ->where('consumption.archive', null)
            ->where('consumption.toCheck', []));
});

it('shares whether the family has a utility with consumption, and answers 404 without one', function (): void {
    $this->actingAs($this->marco)->get('/budget/consumption')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('has_consumption', true));

    $family = Family::factory()->create();
    $alone = User::factory()->create(['family_id' => $family->id, 'role' => Role::Admin]);
    CurrentFamily::using($family->id, static fn (): Utility => Utility::factory()->create(['family_id' => $family->id, 'type' => UtilityType::Internet]));

    $this->actingAs($alone)->get(route('settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('has_consumption', false));
    $this->actingAs($alone)->get('/budget/consumption')->assertNotFound();
});

it('counts the bills still to read and lists those to check', function (): void {
    app()->instance(DocumentReader::class, new FakeDocumentReader);
    $electricity = consumptionPageUtility(UtilityType::Electricity);
    consumptionPageBill($electricity, 'Bolletta luce di marzo');
    consumptionPageBill($electricity, 'Foto della bolletta di aprile', 'image/jpeg');
    consumptionPageBill($electricity, 'Contratto', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $notABill = consumptionPageBill($electricity, 'Lettera');
    consumptionPageReading($notABill, ConsumptionOutcome::NotABill);
    $toCheck = consumptionPageBill($electricity, 'Bolletta luce di febbraio');
    consumptionPageReading($toCheck, ConsumptionOutcome::Saved, '2026-11-19 10:00');
    consumptionPageReading($toCheck, ConsumptionOutcome::WrongUnit);

    $this->actingAs($this->marco)->get('/budget/consumption')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('consumption.archive.count', 4)
            ->where('consumption.archive.running', false)
            ->where('consumption.archive.can_read', true)
            ->has('consumption.archive.cost')
            ->has('consumption.archive.left')
            ->where('consumption.toCheck', [[
                'document_id' => $toCheck->id,
                'title' => 'Bolletta luce di febbraio',
                'date' => '2025-03-10',
                'reason' => 'Unità sbagliata',
            ]]));
});

it('estimates the cost of the archive from the consumption readings, else from the document readings', function (): void {
    $electricity = consumptionPageUtility(UtilityType::Electricity);
    consumptionPageBill($electricity, 'Bolletta luce di marzo');
    consumptionPageBill($electricity, 'Bolletta luce di aprile');
    $familyId = $this->marco->family_id;
    AiReading::query()->withoutGlobalScopes()->where('family_id', $familyId)->delete();
    AiReading::factory()->create(['family_id' => $familyId, 'purpose' => ReadingPurpose::Document, 'cost_usd' => '0.010000']);

    $this->actingAs($this->marco)->get('/budget/consumption')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('consumption.archive.cost', 0.04));

    AiReading::factory()->create(['family_id' => $familyId, 'purpose' => ReadingPurpose::Consumption, 'cost_usd' => '0.003000']);

    $this->actingAs($this->marco)->get('/budget/consumption')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('consumption.archive.cost', 0.012));
});

it('does not offer to read the archive while the reading is off', function (): void {
    app()->instance(DocumentReader::class, (new FakeDocumentReader)->off());
    consumptionPageBill(consumptionPageUtility(UtilityType::Electricity), 'Bolletta luce di marzo');

    $this->actingAs($this->marco)->get('/budget/consumption')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('consumption.archive.count', 3)
            ->where('consumption.archive.can_read', false));
});
