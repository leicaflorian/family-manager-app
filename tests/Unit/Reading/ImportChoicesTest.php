<?php

declare(strict_types=1);

use App\Data\Reading\ImportChoices;
use App\Enums\RecordKind;

describe('cleanOverrides', function (): void {
    test('an expense takes the deadline, checked as a date; emptied, it is null', function (): void {
        expect(ImportChoices::fromArray(['overrides' => ['movement' => ['due_date' => '2026-11-30']]])->movementOverrides('expense'))->toBe(['due_date' => '2026-11-30'])
            ->and(ImportChoices::fromArray(['overrides' => ['movement' => ['due_date' => '']]])->movementOverrides('expense'))->toBe(['due_date' => null])
            ->and(ImportChoices::fromArray(['overrides' => ['movement' => ['due_date' => '2026-02-30']]])->movementOverrides('expense'))->toBe([]);
    });

    test('an income has no deadline', function (): void {
        expect(ImportChoices::fromArray(['overrides' => ['movement' => ['due_date' => '2026-11-30']]])->movementOverrides('income'))->toBe([]);
    });

    test('an amount or a date emptied leave the read ones; a fee only for an expense and never below zero', function (): void {
        $choices = ImportChoices::fromArray(['overrides' => ['movement' => [
            'amount' => '', 'date' => null, 'title' => '  Dentista  ', 'fee_amount' => '1.004', 'organization_id' => '7', 'category_id' => 'x',
        ]]]);

        expect($choices->movementOverrides('expense'))->toBe(['title' => 'Dentista', 'organization_id' => 7, 'fee_amount' => 1.0])
            ->and($choices->movementOverrides('income'))->toBe(['title' => 'Dentista', 'organization_id' => 7])
            ->and(ImportChoices::fromArray(['overrides' => ['movement' => ['fee_amount' => -1, 'amount' => '84.204']]])->movementOverrides('expense'))->toBe(['amount' => 84.2]);
    });

    test('an event type emptied leaves the read one; an unknown type is ignored', function (): void {
        expect(ImportChoices::fromArray(['overrides' => ['event' => ['type' => '', 'outcome' => '', 'next_checkup_date' => '2027-04-22']]])->overrides['event'])
            ->toBe(['outcome' => null, 'next_checkup_date' => '2027-04-22'])
            ->and(ImportChoices::fromArray(['overrides' => ['event' => ['type' => 'surgery', 'title' => 'Visita']]])->overrides['event'])->toBe(['title' => 'Visita'])
            ->and(ImportChoices::fromArray(['overrides' => ['event' => ['type' => 'visit']]])->overrides['event'])->toBe(['type' => 'visit']);
    });
});

describe('cleanTargets', function (): void {
    test('new, an id, or auto for anything else', function (): void {
        $choices = ImportChoices::fromArray(['targets' => ['movement' => 12, 'event' => 'new']]);

        expect($choices->targets['movement'])->toBe(12)
            ->and($choices->targets['event'])->toBe('new')
            ->and(ImportChoices::fromArray(['targets' => ['movement' => -3, 'event' => 'x']])->targets)->toBe(['movement' => 'auto', 'event' => 'auto', 'records' => []]);
    });
});

describe('cleanRecordEdits', function (): void {
    test('keeps only the fields of each record, checked', function (): void {
        $edits = ImportChoices::fromArray(['overrides' => ['records' => [
            'utility' => ['type' => 'gas', 'supply_point_code' => ' 12345678901234 ', 'home_id' => '5', 'activation_date' => '2026-10-01', 'person_id' => 9, 'notes' => 'x'],
            'belonging' => ['name' => 'Lavatrice', 'purchase_price' => '549.004', 'warranty_expiry_date' => '03/10/2028'],
            'organization' => ['name' => 'Edison', 'types' => 'utility_provider'],
            'vehicle' => ['registration_year' => 2019.5, 'plate_number' => 'AB123CD'],
            'nonsense' => ['name' => 'x'],
        ]]])->overrides['records'];

        expect($edits)->toBe([
            'organization' => ['name' => 'Edison', 'types' => ['utility_provider']],
            'vehicle' => ['plate_number' => 'AB123CD'],
            'utility' => ['type' => 'gas', 'supply_point_code' => '12345678901234', 'home_id' => 5, 'activation_date' => '2026-10-01'],
            'belonging' => ['name' => 'Lavatrice', 'purchase_price' => 549.0],
        ]);
    });

    test('an empty value clears the field, an invalid choice is ignored', function (): void {
        expect(ImportChoices::fromArray(['overrides' => ['records' => ['utility' => ['type' => 'nuclear', 'customer_code' => ''], 'policy' => ['type' => '']]]])->overrides['records'])
            ->toBe(['utility' => ['customer_code' => null], 'policy' => ['type' => null]]);
    });

    test('the home of a policy is kept', function (): void {
        expect(ImportChoices::fromArray(['overrides' => ['records' => ['policy' => ['home_id' => 2]]]])->overrides['records'])->toBe(['policy' => ['home_id' => 2]])
            ->and(ImportChoices::fromArray(['overrides' => ['records' => ['policy' => ['title' => ' Allianz Hybrid Plan ']]]])->overrides['records'])->toBe(['policy' => ['title' => 'Allianz Hybrid Plan']]);
    });

    test('nothing sent: nothing', function (): void {
        expect(ImportChoices::fromArray([])->overrides['records'])->toBe([])
            ->and(ImportChoices::fromArray(['overrides' => ['records' => 'x']])->overrides['records'])->toBe([]);
    });
});

describe('cleanRecordTargets', function (): void {
    test('new, an id, or auto for anything else; unknown records dropped', function (): void {
        expect(ImportChoices::fromArray(['targets' => ['records' => ['utility' => 'new', 'belonging' => 12, 'maintenance' => 'x', 'policy' => -1, 'nonsense' => 'new']]])->targets['records'])
            ->toBe(['utility' => 'new', 'policy' => 'auto', 'belonging' => 12, 'maintenance' => 'auto']);
    });

    test('nothing sent: nothing', function (): void {
        expect(ImportChoices::fromArray(['targets' => ['records' => null]])->targets['records'])->toBe([]);
    });
});

describe('the boxes', function (): void {
    test('the ticked boxes, "to pay" and the policy, with their defaults', function (): void {
        $choices = ImportChoices::fromArray([
            'document' => ['title' => 'Fattura', 'secret' => 'x'],
            'movement' => true, 'event' => false, 'records' => ['organization', 'nonsense', 'organization', 'policy'],
            'due' => false, 'policy' => 'file:3',
        ]);

        expect($choices->document)->toBe(['title' => 'Fattura'])
            ->and($choices->movement)->toBeTrue()
            ->and($choices->event)->toBeFalse()
            ->and($choices->records)->toBe([RecordKind::Organization, RecordKind::Policy])
            ->and($choices->due)->toBeFalse()
            ->and($choices->policy)->toBe('file:3');

        $empty = ImportChoices::fromArray([]);

        expect($empty->movement)->toBeFalse()
            ->and($empty->due)->toBeNull()
            ->and($empty->policy)->toBe('auto')
            ->and(ImportChoices::fromArray(['policy' => 'whatever'])->policy)->toBe('auto')
            ->and(ImportChoices::fromArray(['policy' => 'none'])->policy)->toBe('none');
    });
});
