<?php

declare(strict_types=1);

use App\Data\Reading\Extraction;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingPrompt;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

/**
 * A context with the given lists.
 *
 * @param  array<string, mixed>  $lists
 */
function readingContext(array $lists = []): ReadingContext
{
    return new ReadingContext(...[
        'expenseCategories' => [],
        'documentCategories' => [],
        'organizations' => [],
        'people' => [],
        'fileName' => null,
        'fileNote' => null,
        ...$lists,
    ]);
}

describe('parseExtraction', function (): void {
    test('parses valid JSON', function (): void {
        $x = ReadingPrompt::parse('{"kind":"receipt","confidence":"high"}', readingContext());

        expect($x)->toBeInstanceOf(Extraction::class)
            ->and($x->kind)->toBe('receipt')
            ->and($x->confidence)->toBe('high');
    });

    // wanted: the providers are asked for the schema and answer with pure JSON, so code fences are not stripped
    test('JSON inside a code block is refused: the providers answer with the schema, pure JSON', function (): void {
        expect(fn (): Extraction => ReadingPrompt::parse("```json\n{\"kind\":\"receipt\"}\n```", readingContext()))
            ->toThrow(ReadingFailed::class, 'The answer of the model is not valid.');
    });

    test('text that is not JSON is refused with a ReadingFailed', function (): void {
        expect(fn (): Extraction => ReadingPrompt::parse('non è json', readingContext()))
            ->toThrow(ReadingFailed::class, 'The answer of the model is not valid.');
    });

    test('JSON that is not an object is refused', function (): void {
        expect(fn (): Extraction => ReadingPrompt::parse('[1,2]', readingContext()))
            ->toThrow(ReadingFailed::class, 'The answer of the model is not valid.')
            ->and(fn (): Extraction => ReadingPrompt::parse('"text"', readingContext()))
            ->toThrow(ReadingFailed::class, 'The answer of the model is not valid.');
    });

    test('null and empty text are refused', function (): void {
        expect(fn (): Extraction => ReadingPrompt::parse(null, readingContext()))
            ->toThrow(ReadingFailed::class, 'The answer of the model is empty.')
            ->and(fn (): Extraction => ReadingPrompt::parse('', readingContext()))
            ->toThrow(ReadingFailed::class, 'The answer of the model is empty.');
    });

    test('an answer that already is an array is read as it is', function (): void {
        $x = ReadingPrompt::parse(['kind' => 'invoice', 'total_amount' => 84.2, 'confidence' => 'low'], readingContext());

        expect($x->total_amount)->toBe(84.2)->and($x->kind)->toBe('invoice');
    });

    test('an answer that is not retried carries no retry', function (): void {
        try {
            ReadingPrompt::parse('', readingContext());
        } catch (ReadingFailed $failure) {
            expect($failure->retry)->toBeFalse();
        }
    });

    test('an id that is not in the lists is dropped', function (): void {
        $context = readingContext([
            'organizations' => [['id' => 3, 'label' => 'Enel']],
            'people' => [['id' => 4, 'label' => 'Anna Rossi']],
            'expenseCategories' => [['id' => 1, 'label' => 'Casa › Luce']],
            'documentCategories' => [['id' => 2, 'label' => 'Auto']],
        ]);

        $kept = ReadingPrompt::parse(json_encode([
            'kind' => 'receipt', 'organization_id' => 3, 'person_id' => 4, 'expense_category_id' => 1, 'document_category_id' => 2,
        ]), $context);
        $dropped = ReadingPrompt::parse(json_encode([
            'kind' => 'receipt', 'organization_id' => 99, 'person_id' => 98, 'expense_category_id' => 97, 'document_category_id' => 96,
        ]), $context);

        expect([$kept->organization_id, $kept->person_id, $kept->expense_category_id, $kept->document_category_id])->toBe([3, 4, 1, 2])
            ->and([$dropped->organization_id, $dropped->person_id, $dropped->expense_category_id, $dropped->document_category_id])
            ->toBe([null, null, null, null]);
    });
});

describe('instructions', function (): void {
    test('lists the labels of the choices of the context', function (): void {
        $text = ReadingPrompt::instructions(readingContext([
            'expenseCategories' => [['id' => 1, 'label' => 'Casa > Luce']],
            'documentCategories' => [['id' => 2, 'label' => 'Auto']],
            'organizations' => [['id' => 3, 'label' => 'Enel']],
            'people' => [['id' => 4, 'label' => 'Anna Rossi']],
        ]), 'it');

        expect($text)->toContain("Organizations:\n3: Enel")
            ->toContain("Expense categories:\n1: Casa > Luce")
            ->toContain("Document categories:\n2: Auto")
            ->toContain("People:\n4: Anna Rossi");
    });

    test('an empty list is marked (none)', function (): void {
        expect(ReadingPrompt::instructions(readingContext(), 'it'))->toContain("People:\n(none)");
    });

    test('what was written about the file goes after the lists, before the request', function (): void {
        $text = ReadingPrompt::instructions(readingContext([
            'people' => [['id' => 4, 'label' => 'Anna Rossi']],
            'fileName' => 'Disegno di Luca.pdf',
            'fileNote' => 'Caricato dal telefono il 25/10/2026 da Anna Rossi: «per la nonna»',
        ]), 'it');

        expect($text)->toContain("People:\n4: Anna Rossi\n\nWhat was written about this file when it was uploaded (file name and description):\n"
            ."Disegno di Luca.pdf\nCaricato dal telefono il 25/10/2026 da Anna Rossi: «per la nonna»\n\nExtract the data from this document.");
    });

    test('without a name or a description the request is the same as before', function (): void {
        expect(ReadingPrompt::instructions(readingContext(['fileName' => null, 'fileNote' => '  ']), 'it'))
            ->toBe(ReadingPrompt::instructions(readingContext(), 'it'));
    });

    test('the title is asked in the language of who uploaded', function (): void {
        expect(ReadingPrompt::instructions(readingContext(), 'it'))->toContain('Write `title` and `summary` in Italian.')
            ->and(ReadingPrompt::instructions(readingContext(), 'en'))->toContain('Write `title` and `summary` in English.');
    });
});

describe('fileNotes', function (): void {
    test('the name and the description, one per line', function (): void {
        expect(ReadingPrompt::fileNotes('Telefono 2026-10-25 12.17.pdf', 'Caricato dal telefono il 25/10/2026 da Anna Rossi: «Luca - per la nonna»'))
            ->toBe(['Telefono 2026-10-25 12.17.pdf', 'Caricato dal telefono il 25/10/2026 da Anna Rossi: «Luca - per la nonna»']);
    });

    // The files table has no title (Directus made it from the name): only the name and the note are left to keep
    test('a title that only repeats the name is left out, a different one is kept', function (): void {
        expect(ReadingPrompt::fileNotes('  bolletta_enel-ottobre.pdf ', null))->toBe(['bolletta_enel-ottobre.pdf'])
            ->and(ReadingPrompt::fileNotes('scan.pdf', ' Visita oculistica di Luca '))->toBe(['scan.pdf', 'Visita oculistica di Luca']);
    });

    test('empty values give no lines', function (): void {
        expect(ReadingPrompt::fileNotes('', "\n"))->toBe([])
            ->and(ReadingPrompt::fileNotes(null, null))->toBe([]);
    });
});

describe('withRecordFields', function (): void {
    test('a reading made before the record fields existed gets them as null, the rest unchanged', function (): void {
        $x = Extraction::from(['kind' => 'receipt', 'title' => 'Lavatrice', 'confidence' => 'high']);

        foreach (Extraction::RECORD_FIELDS as $field) {
            expect($x->{$field})->toBeNull();
        }
        expect($x->title)->toBe('Lavatrice')->and($x->kind)->toBe('receipt');
    });

    test('values already read are kept', function (): void {
        $x = Extraction::from(['kind' => 'receipt', 'durable_good' => true, 'item_brand' => 'Bosch', 'confidence' => 'high']);

        expect($x->durable_good)->toBeTrue()->and($x->item_brand)->toBe('Bosch');
    });
});

describe('schema', function (): void {
    test('asks for every field of the extraction, all required, the ones that can be empty nullable', function (): void {
        $properties = ReadingPrompt::schema(new JsonSchemaTypeFactory);

        expect(array_keys($properties))->toBe(Extraction::FIELDS);

        $kind = $properties['kind']->toArray();
        $total = $properties['total_amount']->toArray();
        $due = $properties['due_date']->toArray();

        expect($kind['enum'])->toBe(Extraction::KINDS)
            ->and($properties['income_type']->toArray()['enum'])->toContain(null)
            ->and($total['type'])->toBe(['number', 'null'])
            ->and($due['format'])->toBe('date')
            ->and($properties['title']->toArray()['type'])->toBe(['string', 'null']);
    });

    test('the system text keeps the rule on the expiry of a bill', function (): void {
        expect(ReadingPrompt::SYSTEM)->toContain('for utility_bill, receipt, invoice, payment_receipt, payslip and road_tax expiry_date is null');
    });
});
