<?php

declare(strict_types=1);

use App\Data\Search\SearchResultData;
use App\Enums\DocumentStatus;
use App\Enums\SearchGroup;
use App\Models\Document;
use App\Models\Family;
use App\Models\File;
use App\Services\Search\DocumentResults;
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
 * Makes a document in the family of the test.
 *
 * @param  array<string, mixed>  $attributes  attributes of the document
 */
function searchDocument(Family $family, string $title, array $attributes = [], ?array $reading = null): Document
{
    return CurrentFamily::using($family->id, function () use ($family, $title, $attributes, $reading): Document {
        $file = File::factory()->create(['family_id' => $family->id]);

        if ($reading !== null) {
            $file->forceFill(['reading' => $reading])->save();
        }

        return Document::factory()->create(['family_id' => $family->id, 'title' => $title, 'file_id' => $file->id, ...$attributes]);
    });
}

/**
 * The results found for some text, inside the family.
 *
 * @return list<SearchResultData>
 */
function documentResults(Family $family, string $text, int $limit = 10): array
{
    return CurrentFamily::using($family->id, fn (): array => resolve(DocumentResults::class)->results(SearchTerms::fromQuery($text), $limit));
}

/**
 * The titles found for some text.
 *
 * @return list<string>
 */
function documentTitles(Family $family, string $text): array
{
    return array_map(static fn (SearchResultData $result): string => $result->title, documentResults($family, $text));
}

it('belongs to the documents group', function (): void {
    expect(resolve(DocumentResults::class)->group())->toBe(SearchGroup::Documents);
});

it('finds documents by title, the most recent first, with the row fields', function (): void {
    searchDocument($this->family, 'Libretto di circolazione Fiat Panda', ['date' => '2020-01-10']);
    $policy = searchDocument($this->family, 'Polizza RC auto Panda 2026', ['date' => '2026-05-01']);

    $results = documentResults($this->family, 'Pan');

    expect(array_map(static fn (SearchResultData $r): string => $r->title, $results))->toBe(['Polizza RC auto Panda 2026', 'Libretto di circolazione Fiat Panda'])
        ->and($results[0]->key)->toBe('document-'.$policy->id)
        ->and($results[0]->group)->toBe(SearchGroup::Documents)
        ->and($results[0]->href)->toBe(route('documents.show', $policy->id))
        ->and($results[0]->date)->toBe('2026-05-01')
        ->and($results[0]->icon)->toBe('file-text')
        ->and($results[0]->tone)->toBe('neutral')
        ->and($results[0]->area)->toBeNull()
        ->and($results[0]->flag)->toBeNull()
        ->and(CurrentFamily::using($this->family->id, fn (): int => resolve(DocumentResults::class)->count(SearchTerms::fromQuery('Pan'))))->toBe(2);
});

it('finds by organization and shows its name as subtitle', function (): void {
    $results = documentResults($this->family, 'UnipolSai');

    expect(array_map(static fn (SearchResultData $r): string => $r->title, $results))->toBe(['Polizza RC auto 2026'])
        ->and($results[0]->subtitle)->toBe('UnipolSai');
});

it('finds by person name', function (): void {
    expect(documentTitles($this->family, 'Giulia'))->toContain('Iscrizione danza 2026/27');
});

it('finds by notes, tags and the summary of the reading', function (): void {
    searchDocument($this->family, 'Foglio uno', ['notes' => 'quaderno blu']);
    searchDocument($this->family, 'Foglio due', ['tags' => ['gita', 'scuola']]);
    searchDocument($this->family, 'Foglio tre', [], ['summary' => 'Ricevuta del veterinario per Briciola']);

    expect(documentTitles($this->family, 'quaderno'))->toBe(['Foglio uno'])
        ->and(documentTitles($this->family, 'gita'))->toBe(['Foglio due'])
        ->and(documentTitles($this->family, 'veterinario'))->toBe(['Foglio tre']);
});

it('ignores case and accents', function (): void {
    searchDocument($this->family, 'Perché il bollo costa di più', ['date' => '2026-01-01']);
    searchDocument($this->family, 'Foglio con riassunto', [], ['summary' => 'Perché il bollo']);

    expect(documentTitles($this->family, 'perche'))->toContain('Perché il bollo costa di più')
        ->and(documentTitles($this->family, 'PERCHE'))->toContain('Foglio con riassunto')
        ->and(documentTitles($this->family, 'BOLLETTA LUCE'))->toContain('Bolletta luce di ottobre');
});

it('finds the documents of a day typed as a date, and no others', function (): void {
    expect(documentTitles($this->family, '23/09/2026'))->toBe(['Busta paga di settembre']);
});

it('puts archived documents after the others with a flag', function (): void {
    searchDocument($this->family, 'Zeta vecchio', ['date' => '2030-01-01', 'status' => DocumentStatus::Archived]);
    searchDocument($this->family, 'Zeta attivo', ['date' => '2020-01-01']);
    searchDocument($this->family, 'Zeta senza data', ['date' => null]);

    $results = documentResults($this->family, 'Zeta');

    expect(array_map(static fn (SearchResultData $r): string => $r->title, $results))->toBe(['Zeta attivo', 'Zeta senza data', 'Zeta vecchio'])
        ->and($results[2]->flag)->toBe('Archived')
        ->and($results[0]->flag)->toBeNull();
});

it('respects the limit', function (): void {
    expect(documentResults($this->family, 'Bolletta', 2))->toHaveCount(2);
});

it('does not treat a percent sign as a wildcard', function (): void {
    expect(documentResults($this->family, '%'))->toBe([]);
});

it('does not find the documents of another family', function (): void {
    $other = Family::factory()->create();
    searchDocument($other, 'Documento segreto');

    expect(documentTitles($this->family, 'segreto'))->toBe([])
        ->and(documentTitles($other, 'segreto'))->toBe(['Documento segreto'])
        ->and(documentTitles($other, 'Bolletta'))->toBe([]);
});

it('finds by tags ignoring case and accents', function (): void {
    searchDocument($this->family, 'Foglio tag', ['tags' => ['Scuola', 'perché']]);

    expect(documentTitles($this->family, 'scuola'))->toContain('Foglio tag')
        ->and(documentTitles($this->family, 'perche'))->toBe(['Foglio tag'])
        ->and(documentTitles($this->family, 'PERCHÉ'))->toBe(['Foglio tag']);
});

it('matches a date word as the date of the document and the other words as text', function (): void {
    searchDocument($this->family, 'Perché il bollo', ['date' => '2026-09-23']);
    searchDocument($this->family, 'Altro bollo', ['date' => '2026-01-05']);

    expect(documentTitles($this->family, 'bollo 23/09/2026'))->toBe(['Perché il bollo'])
        ->and(documentTitles($this->family, '23/09/2026 bollo'))->toBe(['Perché il bollo']);
});
