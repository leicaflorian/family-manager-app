<?php

declare(strict_types=1);

use App\Data\Reading\ImportChoices;
use App\Enums\EnrollmentType;
use App\Enums\FilingType;
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

    test('an amount below zero or above the bound of the forms, or a text longer than 255, is dropped', function (): void {
        expect(ImportChoices::fromArray(['overrides' => ['movement' => ['amount' => -5, 'title' => str_repeat('a', 256)]]])->movementOverrides('expense'))->toBe([])
            ->and(ImportChoices::fromArray(['overrides' => ['movement' => ['amount' => 100000000]]])->movementOverrides('expense'))->toBe([])
            ->and(ImportChoices::fromArray(['overrides' => ['movement' => ['amount' => 99999999, 'title' => str_repeat('a', 255)]]])->movementOverrides('expense'))
            ->toBe(['title' => str_repeat('a', 255), 'amount' => 99999999.0]);
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

describe('the fields of the card of the category', function (): void {
    test('the five cards and the organization keep their fixed list', function (): void {
        expect(ImportChoices::recordFields(RecordKind::Vehicle, null))->toBe(ImportChoices::RECORD_FIELDS['vehicle'])
            ->and(ImportChoices::recordFields(RecordKind::Organization, 'home'))->toBe(ImportChoices::RECORD_FIELDS['organization']);
    });

    test('a card of the category has the required fields of its class that are not the document\'s, checked by their kind', function (): void {
        expect(ImportChoices::recordFields(RecordKind::Card, 'home'))->toBe(['name' => 'text'])
            ->and(ImportChoices::recordFields(RecordKind::Card, 'filing'))->toBe(['title' => 'text', 'type' => 'choice:'.FilingType::class])
            ->and(ImportChoices::recordFields(RecordKind::Card, 'enrollment'))->toBe(['type' => 'choice:'.EnrollmentType::class])
            ->and(ImportChoices::recordFields(RecordKind::Card, null))->toBe([])
            ->and(ImportChoices::recordFields(RecordKind::Card, 'nonsense'))->toBe([]);
    });

    test('what was written in the card box is cleaned with the fields of the type, the others dropped', function (): void {
        $choices = ImportChoices::fromArray(['overrides' => ['records' => [
            'card' => ['title' => ' Dichiarazione 2026 ', 'type' => 'income_tax', 'plate_number' => 'AB123CD', 'person_id' => 4],
            'vehicle' => ['plate_number' => 'AB123CD'],
        ]]]);
        $type = FilingType::cases()[0]->value;
        $withType = ImportChoices::fromArray(['overrides' => ['records' => ['card' => ['type' => $type, 'title' => '']]]]);

        expect($choices->overrides['records'])->toBe(['vehicle' => ['plate_number' => 'AB123CD']])
            ->and($choices->recordOverrides('home'))->toBe(['vehicle' => ['plate_number' => 'AB123CD'], 'card' => []])
            ->and($choices->recordOverrides(null))->toBe(['vehicle' => ['plate_number' => 'AB123CD']])
            ->and($withType->recordOverrides('filing'))->toBe(['card' => ['title' => null, 'type' => $type]])
            ->and(ImportChoices::fromArray(['overrides' => ['records' => ['card' => ['type' => 'nonsense']]]])->recordOverrides('filing'))->toBe(['card' => []]);
    });
});

describe('the card the box was made for', function (): void {
    test('the card picked and the fields written for another card are dropped; the other boxes stay', function (): void {
        $raw = ['overrides' => ['records' => ['card' => ['name' => 'Casa al mare'], 'vehicle' => ['name' => 'Panda']]], 'targets' => ['records' => ['card' => 3, 'vehicle' => 'new']]];
        $forHome = ImportChoices::fromArray([...$raw, 'card_type' => 'home']);

        expect($forHome->recordOverrides('home'))->toBe(['vehicle' => ['name' => 'Panda'], 'card' => ['name' => 'Casa al mare']])
            ->and($forHome->recordTargets('home'))->toBe(['vehicle' => 'new', 'card' => 3])
            ->and($forHome->recordOverrides('subscription'))->toBe(['vehicle' => ['name' => 'Panda']])
            ->and($forHome->recordTargets('subscription'))->toBe(['vehicle' => 'new'])
            ->and(ImportChoices::fromArray([...$raw, 'card_type' => null])->recordTargets('home'))->toBe(['vehicle' => 'new']);
    });

    test('without the card the box was made for, the choices follow the card of the proposal', function (): void {
        $choices = ImportChoices::fromArray(['targets' => ['records' => ['card' => 3]]]);

        expect($choices->recordTargets('subscription'))->toBe(['card' => 3])
            ->and($choices->withOrganization(5)->recordTargets('home'))->toBe(['card' => 3])
            ->and(ImportChoices::fromArray(['targets' => ['records' => ['card' => 3]], 'card_type' => 'home'])->withOrganization(5)->recordTargets('vehicle'))->toBe([]);
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

test('the old policies to cancel are positive ids, each once, and survive the organization', function (): void {
    $choices = ImportChoices::fromArray(['replace_policies' => [4, '9', 4, 0, -2, 'x', null, [3]]]);

    expect($choices->replacePolicies)->toBe([4, 9])
        ->and($choices->withOrganization(5)->replacePolicies)->toBe([4, 9])
        ->and(ImportChoices::fromArray(['replace_policies' => 'x'])->replacePolicies)->toBe([])
        ->and(ImportChoices::fromArray([])->replacePolicies)->toBe([]);
});

describe('consumption', function (): void {
    test('the box is ticked, unticked, or follows the proposal when the page does not say', function (): void {
        expect(ImportChoices::fromArray(['consumption' => true])->consumption)->toBeTrue()
            ->and(ImportChoices::fromArray(['consumption' => '0'])->consumption)->toBeFalse()
            ->and(ImportChoices::fromArray([])->consumption)->toBeNull();
    });

    test('the corrections take a positive quantity with three decimals, dates and the estimate; emptied, they leave the reading', function (): void {
        expect(ImportChoices::fromArray(['overrides' => ['consumption' => [
            'quantity' => '210.12345', 'period_start' => '2026-09-01', 'period_end' => '2026-02-30', 'is_estimated' => 'true', 'unit' => 'smc',
        ]]])->consumptionOverrides())->toBe(['quantity' => 210.123, 'period_start' => '2026-09-01', 'is_estimated' => true])
            ->and(ImportChoices::fromArray(['overrides' => ['consumption' => ['quantity' => 0, 'period_start' => '']]])->consumptionOverrides())->toBe([])
            ->and(ImportChoices::fromArray(['overrides' => ['consumption' => ['quantity' => -4]]])->consumptionOverrides())->toBe([])
            ->and(ImportChoices::fromArray([])->consumptionOverrides())->toBe([]);
    });

    test('the box and its corrections survive the movement picked and the organization filled', function (): void {
        $choices = ImportChoices::fromArray(['consumption' => false, 'overrides' => ['consumption' => ['quantity' => 12]]]);

        expect($choices->withMovementTarget(3)->consumption)->toBeFalse()
            ->and($choices->withOrganization(5)->consumption)->toBeFalse()
            ->and($choices->withMovementTarget(3)->consumptionOverrides())->toBe(['quantity' => 12.0])
            ->and($choices->withOrganization(5)->consumptionOverrides())->toBe(['quantity' => 12.0]);
    });
});

describe('supplies', function (): void {
    test('the choices of each supply, checked as the ones of a bill; a bad index, target or amount is dropped', function (): void {
        $choices = ImportChoices::fromArray(['supplies' => [
            0 => ['movement' => true, 'record' => '1', 'consumption' => false, 'due' => true, 'targets' => ['movement' => 'new', 'record' => '12'],
                'overrides' => ['movement' => ['amount' => '90', 'title' => ' Luce '], 'record' => ['supply_point_code' => ' IT001 ', 'notes' => 'x'], 'consumption' => ['quantity' => '201']]],
            1 => ['record' => true, 'targets' => ['record' => 'abc'], 'overrides' => ['movement' => ['amount' => -5]]],
            'x' => ['movement' => true],
            12 => ['movement' => true],
            2 => 'nonsense',
        ]]);

        expect(array_keys($choices->supplies))->toBe([0, 1])
            ->and($choices->supplies[0])->toBe([
                'movement' => true, 'record' => true, 'consumption' => false, 'due' => true,
                'targets' => ['movement' => 'new', 'record' => 12],
                'overrides' => ['movement' => ['title' => 'Luce', 'amount' => 90.0], 'record' => ['supply_point_code' => 'IT001'], 'consumption' => ['quantity' => 201.0]],
            ])
            ->and($choices->supplies[1])->toBe([
                'movement' => false, 'record' => true, 'consumption' => null, 'due' => null,
                'targets' => ['movement' => 'auto', 'record' => 'auto'],
                'overrides' => ['movement' => [], 'record' => [], 'consumption' => []],
            ])
            ->and(ImportChoices::fromArray(['supplies' => 'x'])->supplies)->toBe([])
            ->and(ImportChoices::fromArray([])->supplies)->toBe([]);
    });

    test('the choices of one supply: the document as it is, the rest of that supply only', function (): void {
        $choices = ImportChoices::fromArray([
            'document' => ['title' => 'Bolletta'], 'movement' => true, 'event' => true, 'records' => ['organization', 'utility'], 'due' => false,
            'overrides' => ['movement' => ['amount' => 165], 'records' => ['utility' => ['type' => 'gas']]], 'targets' => ['movement' => 4, 'records' => ['utility' => 3]],
            'consumption' => true, 'policy' => 'none', 'create_card' => true,
            'supplies' => [1 => ['movement' => true, 'record' => true, 'due' => true, 'consumption' => false, 'targets' => ['movement' => 7, 'record' => 9],
                'overrides' => ['movement' => ['amount' => 69.4], 'record' => ['customer_code' => '31'], 'consumption' => ['quantity' => 48]]]],
        ]);

        $one = $choices->forSupply(1);

        expect($one->document)->toBe(['title' => 'Bolletta'])
            ->and($one->movement)->toBeTrue()
            ->and($one->event)->toBeFalse()
            ->and($one->records)->toBe([RecordKind::Utility])
            ->and($one->due)->toBeTrue()
            ->and($one->consumption)->toBeFalse()
            ->and($one->targets)->toBe(['movement' => 7, 'event' => 'auto', 'records' => ['utility' => 9]])
            ->and($one->overrides)->toBe(['movement' => ['amount' => 69.4], 'event' => [], 'records' => ['utility' => ['customer_code' => '31']], 'consumption' => ['quantity' => 48.0]])
            ->and($one->policy)->toBe('auto')
            ->and($one->createCard)->toBeFalse()
            ->and($one->supplies)->toBe([]);
    });

    test('a supply without choices follows the proposal, as a bill alone', function (): void {
        $one = ImportChoices::fromArray(['document' => ['title' => 'Bolletta'], 'movement' => true, 'records' => ['utility']])->forSupply(5);
        $alone = new ImportChoices(document: ['title' => 'Bolletta']);

        expect($one)->toEqual(new ImportChoices(
            document: ['title' => 'Bolletta'],
            overrides: ['movement' => [], 'event' => [], 'records' => [], 'consumption' => []],
            targets: ['movement' => 'auto', 'event' => 'auto', 'records' => []],
        ))
            ->and($one->movement)->toBe($alone->movement)
            ->and($one->records)->toBe([])
            ->and($one->due)->toBeNull()
            ->and($one->consumption)->toBeNull();
    });

    test('the supplies survive the movement picked and the organization filled', function (): void {
        $choices = ImportChoices::fromArray(['supplies' => [0 => ['movement' => true]]]);

        expect($choices->withMovementTarget(3)->supplies)->toBe($choices->supplies)
            ->and($choices->withOrganization(5)->supplies)->toBe($choices->supplies);
    });
});
