<?php

declare(strict_types=1);

use App\Enums\DocumentCategoryRole;
use App\Enums\DocumentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\VehicleStatus;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employment;
use App\Models\Filing;
use App\Models\FinancialAccount;
use App\Models\Person;
use App\Models\Vehicle;
use App\Services\Reminders\ExpectedDocuments;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Date::setTestNow(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->today = CarbonImmutable::today('Europe/Rome');
});

afterEach(fn () => Date::setTestNow());

/**
 * A document of the Bianchi linked to a card.
 *
 * @param  Model  $card  the card
 * @param  array<string, mixed>  $state  the columns of the document
 */
function expectedLinkedDocument($card, array $state): Document
{
    $document = Document::factory()->create(['family_id' => CurrentFamily::id(), ...$state]);
    $document->morphedByMany($card::class, 'documentable')->attach($card);

    return $document;
}

test('an active vehicle expects its registration document, found by the category with the role, or one under it', function (): void {
    insideBianchi(function (): void {
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        $category = DocumentCategory::withRole(DocumentCategoryRole::VehicleRegistration);

        expect($category)->not->toBeNull()
            ->and(resolve(ExpectedDocuments::class)->for($panda, $this->today))->toBe(['expected' => 1, 'missing' => ['Vehicle registration document']]);

        $sub = DocumentCategory::factory()->create(['family_id' => CurrentFamily::id(), 'parent_id' => $category->id]);
        expectedLinkedDocument($panda, ['title' => 'Carta di circolazione', 'category_id' => $sub->id]);
        expect(resolve(ExpectedDocuments::class)->for($panda->fresh(), $this->today))->toBe(['expected' => 1, 'missing' => []]);

        $other = Vehicle::factory()->create(['family_id' => CurrentFamily::id()]);
        expectedLinkedDocument($other, ['title' => 'Registration', 'category_id' => $category->id]);
        expect(resolve(ExpectedDocuments::class)->for($other, $this->today)['missing'])->toBe([]);
    });
});

test('a document titled «Libretto» in another category is not enough any more', function (): void {
    insideBianchi(function (): void {
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        $vehicles = DocumentCategory::query()->where('card_type', 'vehicle')->whereNull('parent_id')->sole();
        expectedLinkedDocument($panda, ['title' => 'Libretto Panda', 'category_id' => $vehicles->id]);

        expect(resolve(ExpectedDocuments::class)->for($panda->fresh(), $this->today)['missing'])->toBe(['Vehicle registration document']);
    });
});

test('without the category with the role the vehicle expects nothing, and a renamed category still counts', function (): void {
    insideBianchi(function (): void {
        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        $category = DocumentCategory::withRole(DocumentCategoryRole::VehicleRegistration);
        $category->update(['name' => 'Libretti']);
        expectedLinkedDocument($panda, ['category_id' => $category->id]);

        expect(resolve(ExpectedDocuments::class)->for($panda->fresh(), $this->today))->toBe(['expected' => 1, 'missing' => []]);

        $panda->documents()->detach();
        $category->delete();

        expect(resolve(ExpectedDocuments::class)->for($panda->fresh(), $this->today))->toBe(['expected' => 0, 'missing' => []])
            ->and(collect(resolve(ExpectedDocuments::class)->missingFor($this->today))->where('card', 'Fiat Panda')->count())->toBe(0);
    });
});

test('a sold vehicle and a card without expected documents expect nothing', function (): void {
    insideBianchi(function (): void {
        $sold = Vehicle::factory()->create(['family_id' => CurrentFamily::id(), 'status' => VehicleStatus::Sold]);

        expect(resolve(ExpectedDocuments::class)->for($sold, $this->today))->toBe(['expected' => 0, 'missing' => []])
            ->and(resolve(ExpectedDocuments::class)->for(Person::query()->first(), $this->today))->toBe(['expected' => 0, 'missing' => []]);
    });
});

test('the ISEE 2027 of Laura, as seeded, has 3 documents of 4 and misses the average balance, which counts once linked', function (): void {
    insideBianchi(function (): void {
        $filing = Filing::query()->where('title', 'ISEE 2027')->sole();

        expect(resolve(ExpectedDocuments::class)->for($filing, $this->today))->toBe([
            'expected' => 4,
            'missing' => ['Average balance 2025 of Conto BPER'],
        ]);

        expectedLinkedDocument($filing, ['title' => 'Giacenza media al 31/12/2025', 'date' => '2026-01-10']);

        expect(resolve(ExpectedDocuments::class)->for($filing->fresh(), $this->today))->toBe(['expected' => 4, 'missing' => []]);
    });
});

test('an identity card that has expired, or a CU of another person or year, does not count', function (): void {
    insideBianchi(function (): void {
        $filing = Filing::query()->where('title', 'ISEE 2027')->sole();
        $filing->documents()->detach();
        $cu = DocumentCategory::query()->where('name', 'CU')->sole();
        $marco = Person::query()->where('first_name', 'Marco')->sole();
        $laura = Person::query()->where('first_name', 'Laura')->sole();

        expectedLinkedDocument($filing, ['title' => 'CU Marco 2024', 'category_id' => $cu->id, 'person_id' => $marco->id, 'date' => '2025-03-20']);
        expectedLinkedDocument($filing, ['title' => 'CU di Laura', 'category_id' => $cu->id, 'person_id' => $marco->id, 'date' => '2026-03-20']);
        expectedLinkedDocument($filing, ['title' => 'Identity card', 'person_id' => $laura->id, 'expiry_date' => '2026-10-01', 'status' => DocumentStatus::Expired]);

        expect(resolve(ExpectedDocuments::class)->for($filing, $this->today)['missing'])->toBe([
            'Average balance 2025 of Conto BPER',
            'Annual income statement 2026 of Laura',
            'Identity card of Laura',
        ]);
    });
});

test('a filing that is not an ISEE, or has no reference year, expects nothing; without an applicant there is no identity card', function (): void {
    insideBianchi(function (): void {
        $filing = Filing::query()->where('title', 'ISEE 2027')->sole();
        $filing->update(['reference_year' => null]);
        expect(resolve(ExpectedDocuments::class)->for($filing->fresh(), $this->today)['expected'])->toBe(0);

        $filing->update(['reference_year' => 2027, 'person_id' => null]);
        expect(resolve(ExpectedDocuments::class)->for($filing->fresh(), $this->today)['expected'])->toBe(3);

        $filing->update(['type' => 'tax_return']);
        expect(resolve(ExpectedDocuments::class)->for($filing->fresh(), $this->today)['expected'])->toBe(0);
    });
});

test('missingFor lists what is missing in every active vehicle and open ISEE, with the page that uploads it', function (): void {
    insideBianchi(function (): void {
        $missing = resolve(ExpectedDocuments::class)->missingFor($this->today);

        $panda = Vehicle::query()->where('name', 'Fiat Panda')->sole();
        $filing = Filing::query()->where('title', 'ISEE 2027')->sole();

        $category = DocumentCategory::withRole(DocumentCategoryRole::VehicleRegistration);

        expect($missing[0])->toBe(['card' => 'Fiat Panda', 'href' => '/upload?link=vehicle%3A'.$panda->id.'&category='.$category->id, 'document' => 'Vehicle registration document'])
            ->and(collect($missing)->where('card', 'ISEE 2027')->pluck('document')->all())->toBe(['Average balance 2025 of Conto BPER'])
            ->and(collect($missing)->firstWhere('card', 'ISEE 2027')['href'])->toBe('/upload?link=filing%3A'.$filing->id);

        expectedLinkedDocument($panda, ['title' => 'Libretto', 'category_id' => $category->id]);
        expect(collect(resolve(ExpectedDocuments::class)->missingFor($this->today))->where('card', 'Fiat Panda')->count())->toBe(0);
    });
});

test('the category with the role is looked for once, however many vehicles miss their registration document', function (): void {
    insideBianchi(function (): void {
        Vehicle::factory()->count(2)->create(['family_id' => CurrentFamily::id()]);
        $expected = resolve(ExpectedDocuments::class);

        DB::enableQueryLog();
        $missing = $expected->missingFor($this->today);
        $lookups = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from `document_categories` where `role` = ?'));
        DB::disableQueryLog();

        expect(collect($missing)->where('document', 'Vehicle registration document'))->toHaveCount(3)
            ->and($lookups)->toHaveCount(1);
    });
});

test('two accounts with the same name, or a person with two jobs, are two documents each', function (): void {
    insideBianchi(function (): void {
        $filing = Filing::query()->where('title', 'ISEE 2027')->sole();
        $account = FinancialAccount::query()->where('name', 'Conto BPER')->sole();
        $job = Employment::query()->where('status', EmploymentStatus::Active)->firstOrFail();
        $account->replicate()->save();
        $job->replicate()->save();

        $result = resolve(ExpectedDocuments::class)->for($filing, $this->today);

        expect($result['expected'])->toBe(6)
            ->and(array_count_values($result['missing'])['Average balance 2025 of Conto BPER'])->toBe(2);
    });
});
