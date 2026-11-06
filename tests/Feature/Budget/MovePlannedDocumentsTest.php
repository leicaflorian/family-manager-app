<?php

declare(strict_types=1);

use App\Actions\Budget\MovePlannedDocuments;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Family;
use App\Models\PlannedExpense;
use App\Support\CurrentFamily;
use Carbon\CarbonImmutable;

/**
 * Runs the action for a planned expense and a due date, inside a new family; returns the ids that moved.
 *
 * @param  array<string, mixed>  $plannedColumns  Columns of the planned expense.
 * @param  list<array{date: ?string, linked?: bool}>  $documents  Documents waiting on it.
 * @return array{moved: list<int>, ids: list<int>}
 */
function moveDocumentsFor(array $plannedColumns, string $due, array $documents): array
{
    $familyId = Family::factory()->create()->id;

    return CurrentFamily::using($familyId, function () use ($familyId, $plannedColumns, $due, $documents): array {
        $planned = PlannedExpense::factory()->create([...$plannedColumns, 'family_id' => $familyId]);
        $expense = Expense::factory()->create(['family_id' => $familyId]);
        $ids = [];

        foreach ($documents as $row) {
            $document = Document::factory()->create(['family_id' => $familyId, 'planned_expense_id' => $planned->id, 'date' => $row['date']]);

            if ($row['linked'] ?? false) {
                Expense::factory()->create(['family_id' => $familyId])->documents()->attach($document);
            }

            $ids[] = $document->id;
        }

        resolve(MovePlannedDocuments::class)->handle($planned, CarbonImmutable::parse($due), $expense);

        return ['moved' => $expense->documents()->pluck('documents.id')->sort()->values()->all(), 'ids' => $ids];
    });
}

test('a recurring commitment takes the documents from a period before the expense to half a period after', function (): void {
    $result = moveDocumentsFor(
        ['title' => 'Luce', 'is_recurring' => true, 'start_date' => '2026-01-10'],
        '2026-11-10',
        [['date' => '2026-10-20'], ['date' => '2026-09-20'], ['date' => '2026-11-20'], ['date' => '2026-11-28']],
    );

    expect($result['moved'])->toBe([$result['ids'][0], $result['ids'][2]]);
});

test('a recurring commitment leaves out documents already linked to an expense or without a date', function (): void {
    $result = moveDocumentsFor(
        ['title' => 'Luce', 'is_recurring' => true, 'start_date' => '2026-01-10'],
        '2026-11-10',
        [['date' => '2026-10-20', 'linked' => true], ['date' => null], ['date' => '2026-11-01']],
    );

    expect($result['moved'])->toBe([$result['ids'][2]]);
});

test('a one-off expense takes all its documents not yet linked, dated or not', function (): void {
    $result = moveDocumentsFor(
        ['title' => 'Cure dentistiche', 'is_recurring' => false, 'start_date' => '2026-11-28'],
        '2026-11-28',
        [['date' => '2026-10-16'], ['date' => '2026-10-20', 'linked' => true], ['date' => null]],
    );

    expect($result['moved'])->toBe([$result['ids'][0], $result['ids'][2]]);
});
