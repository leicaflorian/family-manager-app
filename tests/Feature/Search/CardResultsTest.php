<?php

declare(strict_types=1);

use App\Cards\CardTypes;
use App\Data\Search\SearchResultData;
use App\Enums\Area;
use App\Enums\SearchGroup;
use App\Enums\UtilityStatus;
use App\Enums\UtilityType;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Person;
use App\Models\Utility;
use App\Models\Vehicle;
use App\Services\Search\CardResults;
use App\Support\CurrentFamily;
use App\Support\SearchTerms;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Date::setTestNow(Date::parse('2026-10-26 10:00', 'Europe/Rome'));
    $this->seed(BianchiFamilySeeder::class);
    $this->family = Family::query()->firstOrFail();
});

afterEach(fn () => Date::setTestNow());

/**
 * The titles found for some text, inside the family.
 *
 * @return list<string>
 */
function cardTitles(Family $family, string $text, int $limit = 10): array
{
    return CurrentFamily::using($family->id, fn (): array => array_map(
        static fn (SearchResultData $result): string => $result->title,
        resolve(CardResults::class)->results(SearchTerms::fromQuery($text), $limit),
    ));
}

it('belongs to the cards group', function (): void {
    expect(resolve(CardResults::class)->group())->toBe(SearchGroup::Cards);
});

it('finds the cards by name, in order, with the link and the area', function (): void {
    $results = CurrentFamily::using($this->family->id, fn (): array => resolve(CardResults::class)->results(SearchTerms::fromQuery('Pan'), 10));

    expect(array_map(static fn (SearchResultData $r): string => $r->title, $results))->toBe(['Fiat Panda', 'Finanziamento Panda', 'RC auto Panda']);

    $panda = $results[0];
    $vehicle = CurrentFamily::using($this->family->id, fn () => Vehicle::query()->where('name', 'Fiat Panda')->firstOrFail());
    expect($panda->href)->toBe(route('cards.show', ['vehicle', $vehicle->id]))
        ->and($panda->key)->toBe('card-vehicle-'.$vehicle->id)
        ->and($panda->group)->toBe(SearchGroup::Cards)
        ->and($panda->area)->toBe(CardTypes::for($vehicle)->area()->value)
        ->and($panda->subtitle)->toBe(CardTypes::for($vehicle)->area()->label())
        ->and($panda->icon)->toBeNull()
        ->and($panda->tone)->toBeNull()
        ->and($panda->flag)->toBeNull();
});

it('finds a card by its organization and by its type label', function (): void {
    expect(cardTitles($this->family, 'UnipolSai'))->toBe(['RC auto Panda'])
        ->and(cardTitles($this->family, 'gas'))->toContain('Gas - Enel Energia');
});

it('needs every word to match', function (): void {
    expect(cardTitles($this->family, 'fiat panda'))->toBe(['Fiat Panda'])
        ->and(cardTitles($this->family, 'panda zzzz'))->toBe([]);
});

it('puts closed cards after the active ones with a flag', function (): void {
    CurrentFamily::using($this->family->id, function (): void {
        Utility::query()->where('type', UtilityType::Gas)->firstOrFail()->update(['status' => UtilityStatus::Terminated]);
    });

    $results = CurrentFamily::using($this->family->id, fn (): array => resolve(CardResults::class)->results(SearchTerms::fromQuery('a'), 100));
    $flags = array_map(static fn (SearchResultData $r): bool => $r->flag !== null, $results);
    $firstClosed = array_search(true, $flags, true);

    expect($firstClosed)->not->toBeFalse()
        ->and(array_slice($flags, $firstClosed))->each->toBeTrue()
        ->and($results[$firstClosed]->title)->toBe('Gas - Enel Energia')
        ->and($results[$firstClosed]->flag)->toBe('Closed card');
});

it('says «Chiusa» (singular) on a closed card in Italian', function (): void {
    CurrentFamily::using($this->family->id, function (): void {
        Utility::query()->where('type', UtilityType::Gas)->firstOrFail()->update(['status' => UtilityStatus::Terminated]);
    });
    app()->setLocale('it');

    $flags = CurrentFamily::using($this->family->id, fn (): array => array_map(
        static fn (SearchResultData $r): ?string => $r->flag,
        resolve(CardResults::class)->results(SearchTerms::fromQuery('Enel Gas'), 10),
    ));

    expect($flags)->toBe(['Chiusa']);
});

it('finds a card by the last name of its person', function (): void {
    CurrentFamily::using($this->family->id, function (): void {
        $person = Enrollment::query()->whereNotNull('person_id')->with('person')->firstOrFail()->person;
        assert($person instanceof Person);
        $person->update(['last_name' => 'Zanzibar']);
    });

    expect(cardTitles($this->family, 'Zanzibar'))->not->toBe([]);
});

it('counts every match and cuts the results at the limit', function (): void {
    $terms = SearchTerms::fromQuery('Pan');

    CurrentFamily::using($this->family->id, function () use ($terms): void {
        $group = resolve(CardResults::class);
        expect($group->count($terms))->toBe(3)
            ->and($group->results($terms, 1))->toHaveCount(1);
    });
});

it('does not find the cards of another family', function (): void {
    $other = Family::factory()->create();
    CurrentFamily::using($other->id, fn () => Vehicle::query()->create(['name' => 'Fiat Zebra', 'brand' => 'Fiat']));

    expect(cardTitles($this->family, 'Zebra'))->toBe([])
        ->and(cardTitles($other, 'Zebra'))->toBe(['Fiat Zebra'])
        ->and(cardTitles($other, 'Panda'))->toBe([]);
});

it('searches every card type without an error', function (): void {
    expect(Area::cases())->not->toBeEmpty();

    CurrentFamily::using($this->family->id, function (): void {
        expect(resolve(CardResults::class)->count(SearchTerms::fromQuery('zzzzqq')))->toBe(0);
    });
});
