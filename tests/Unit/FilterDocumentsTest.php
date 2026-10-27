<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Person;
use App\Queries\FilterDocuments;
use Database\Seeders\BianchiFamilySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2026-10-27 10:00:00');
    $this->seed(BianchiFamilySeeder::class);
});

/**
 * The titles of the Bianchi documents that pass the filters, in the order of the list.
 *
 * @param  array<string, mixed>  $filters
 * @return list<string>
 */
function filtered(array $filters): array
{
    return insideBianchi(fn (): array => array_values(resolve(FilterDocuments::class)
        ->apply(Document::query(), $filters)
        ->pluck('title')
        ->all()));
}

/**
 * The id of a main category of the Bianchis.
 */
function mainCategory(string $name): int
{
    return insideBianchi(fn (): int => DocumentCategory::query()->whereNull('parent_id')->where('name', $name)->sole()->id);
}

test('Lavoro gives the four payslips and the CU, subcategories included', function (): void {
    expect(filtered(['category' => mainCategory('Lavoro')]))->toHaveCount(5)
        ->toContain('Certificazione Unica 2026', 'Busta paga di ottobre');
});

test('Laura and Lavoro give her four documents', function (): void {
    $laura = insideBianchi(fn (): int => Person::query()->where('first_name', 'Laura')->sole()->id);

    expect(filtered(['category' => mainCategory('Lavoro'), 'person' => $laura]))->toBe([
        'Busta paga di ottobre',
        'Busta paga di settembre',
        'Busta paga di agosto',
        'Certificazione Unica 2026',
    ]);
});

test('the search ignores capitals and accents, in the title and in the notes', function (): void {
    insideBianchi(fn () => Document::query()->where('title', 'Contratto Edison')->sole()->update(['notes' => 'Offerta Luce Flex, prezzo bloccato']));

    expect(filtered(['search' => 'DANZA']))->toBe(['Iscrizione danza 2026/27'])
        ->and(filtered(['search' => "carta d'identita"]))->toBe(["Carta d'identità"])
        ->and(filtered(['search' => 'prezzo bloccato']))->toBe(['Contratto Edison']);
});

test('the search takes % and _ as they are', function (): void {
    expect(filtered(['search' => '%']))->toBe([])
        ->and(filtered(['search' => '_']))->toBe([]);
});

test('expiring gives the documents that expire within 60 days or have expired', function (): void {
    expect(filtered(['expiring' => true]))->toEqualCanonicalizing([
        'Tessera sanitaria',
        'Polizza RC auto 2026',
        'Certificato medico sportivo',
        'Scontrino TV Samsung',
    ]);
});

test('the status keeps only the documents in that status', function (): void {
    insideBianchi(fn () => Document::query()->where('title', 'Contratto Edison')->sole()->update(['status' => 'archived']));

    expect(filtered(['status' => 'archived']))->toBe(['Contratto Edison']);
});

test('the list starts from the most recent date, then the last added', function (): void {
    expect(array_slice(filtered([]), 0, 3))->toBe(['Bolletta luce di ottobre', 'Fattura Fastweb', 'Bolletta gas di settembre']);
});

test('linked keeps the documents linked to a record, or with false those linked to none', function (): void {
    $giulia = insideBianchi(fn (): int => Person::query()->where('first_name', 'Giulia')->sole()->id);

    expect(filtered(['person' => $giulia, 'linked' => false]))->toEqualCanonicalizing(["Carta d'identità", 'Tessera sanitaria'])
        ->and(filtered(['person' => $giulia, 'linked' => true]))->toEqualCanonicalizing(['Certificato medico sportivo', 'Iscrizione danza 2026/27']);
});
