<?php

declare(strict_types=1);

use App\Enums\UtilityType;
use App\Support\Reading\TypeCategory;

/** The document categories of a family that started in Italian, as the reading lists them. */
const TYPE_CATEGORY_IT = [
    ['id' => 1, 'label' => 'Casa e utenze'],
    ['id' => 2, 'label' => 'Casa e utenze › Luce'],
    ['id' => 3, 'label' => 'Casa e utenze › Gas'],
    ['id' => 4, 'label' => 'Casa e utenze › Acqua'],
    ['id' => 5, 'label' => 'Bollette'],
    ['id' => 6, 'label' => 'Bollette › Internet'],
    ['id' => 7, 'label' => 'Casa e utenze › Canone RAI'],
];

describe('of', function (): void {
    test('the category named as the default category of the type', function (): void {
        expect(TypeCategory::of(UtilityType::Electricity, TYPE_CATEGORY_IT))->toBe(2)
            ->and(TypeCategory::of(UtilityType::Gas, TYPE_CATEGORY_IT))->toBe(3)
            ->and(TypeCategory::of(UtilityType::Water, TYPE_CATEGORY_IT))->toBe(4)
            ->and(TypeCategory::of(UtilityType::TvLicense, TYPE_CATEGORY_IT))->toBe(7);
    });

    test('only the last name of the path counts, written in any case', function (): void {
        expect(TypeCategory::of(UtilityType::Internet, TYPE_CATEGORY_IT))->toBe(6)
            ->and(TypeCategory::of(UtilityType::Gas, [['id' => 9, 'label' => 'Gas › Bombole'], ['id' => 10, 'label' => 'Spese › GAS']]))->toBe(10);
    });

    test('the default names of both languages count, whoever reads: the family has no language of its own', function (): void {
        $english = [['id' => 1, 'label' => 'Home and utilities › Electricity'], ['id' => 2, 'label' => 'Home and utilities › Waste tax (TARI)']];

        app()->setLocale('en');
        expect(TypeCategory::of(UtilityType::Electricity, TYPE_CATEGORY_IT))->toBe(2)
            ->and(TypeCategory::of(UtilityType::Electricity, $english))->toBe(1)
            ->and(TypeCategory::of(UtilityType::WasteTax, $english))->toBe(2);

        app()->setLocale('it');
        expect(TypeCategory::of(UtilityType::Electricity, $english))->toBe(1);
    });

    test('no category with the name gives none', function (): void {
        expect(TypeCategory::of(UtilityType::Phone, TYPE_CATEGORY_IT))->toBeNull()
            ->and(TypeCategory::of(UtilityType::Gas, []))->toBeNull();
    });
});

describe('commonParent', function (): void {
    test('the parent the categories of all the types share', function (): void {
        expect(TypeCategory::commonParent([UtilityType::Electricity, UtilityType::Gas], TYPE_CATEGORY_IT))->toBe(1);
    });

    test('the direct parent, not the main category, in a deeper tree', function (): void {
        $deep = [
            ['id' => 1, 'label' => 'Casa'],
            ['id' => 2, 'label' => 'Casa › Utenze'],
            ['id' => 3, 'label' => 'Casa › Utenze › Luce'],
            ['id' => 4, 'label' => 'Casa › Utenze › Gas'],
        ];

        expect(TypeCategory::commonParent([UtilityType::Electricity, UtilityType::Gas], $deep))->toBe(2);
    });

    test('types under different parents share none', function (): void {
        expect(TypeCategory::commonParent([UtilityType::Electricity, UtilityType::Internet], TYPE_CATEGORY_IT))->toBeNull();
    });

    test('a type without its category, or a category without a parent, gives none', function (): void {
        expect(TypeCategory::commonParent([UtilityType::Electricity, UtilityType::Phone], TYPE_CATEGORY_IT))->toBeNull()
            ->and(TypeCategory::commonParent([], TYPE_CATEGORY_IT))->toBeNull()
            ->and(TypeCategory::commonParent([UtilityType::Electricity, UtilityType::Gas], [['id' => 1, 'label' => 'Luce'], ['id' => 2, 'label' => 'Gas']]))->toBeNull();
    });

    test('a parent missing from the list gives none', function (): void {
        expect(TypeCategory::commonParent([UtilityType::Electricity, UtilityType::Gas], array_slice(TYPE_CATEGORY_IT, 1)))->toBeNull();
    });
});
