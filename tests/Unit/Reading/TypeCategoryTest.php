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
    test('the category named as the type in the language of the family', function (): void {
        expect(TypeCategory::of(UtilityType::Electricity, TYPE_CATEGORY_IT, 'it'))->toBe(2)
            ->and(TypeCategory::of(UtilityType::Gas, TYPE_CATEGORY_IT, 'it'))->toBe(3)
            ->and(TypeCategory::of(UtilityType::Water, TYPE_CATEGORY_IT, 'it'))->toBe(4)
            ->and(TypeCategory::of(UtilityType::TvLicense, TYPE_CATEGORY_IT, 'it'))->toBe(7);
    });

    test('only the last name of the path counts, written in any case', function (): void {
        expect(TypeCategory::of(UtilityType::Internet, TYPE_CATEGORY_IT, 'it'))->toBe(6)
            ->and(TypeCategory::of(UtilityType::Gas, [['id' => 9, 'label' => 'Gas › Bombole'], ['id' => 10, 'label' => 'Spese › GAS']], 'it'))->toBe(10);
    });

    test('in English the English names', function (): void {
        $choices = [['id' => 1, 'label' => 'Home and utilities › Electricity'], ['id' => 2, 'label' => 'Home and utilities › Waste tax (TARI)']];

        expect(TypeCategory::of(UtilityType::Electricity, $choices, 'en'))->toBe(1)
            ->and(TypeCategory::of(UtilityType::WasteTax, $choices, 'en'))->toBe(2)
            ->and(TypeCategory::of(UtilityType::Electricity, $choices, 'it'))->toBeNull();
    });

    test('no category with the name gives none', function (): void {
        expect(TypeCategory::of(UtilityType::Phone, TYPE_CATEGORY_IT, 'it'))->toBeNull()
            ->and(TypeCategory::of(UtilityType::Gas, [], 'it'))->toBeNull();
    });
});

describe('commonMain', function (): void {
    test('the main category the categories of all the types share', function (): void {
        expect(TypeCategory::commonMain([UtilityType::Electricity, UtilityType::Gas], TYPE_CATEGORY_IT, 'it'))->toBe(1);
    });

    test('types under different main categories share none', function (): void {
        expect(TypeCategory::commonMain([UtilityType::Electricity, UtilityType::Internet], TYPE_CATEGORY_IT, 'it'))->toBeNull();
    });

    test('a type without its category gives none', function (): void {
        expect(TypeCategory::commonMain([UtilityType::Electricity, UtilityType::Phone], TYPE_CATEGORY_IT, 'it'))->toBeNull()
            ->and(TypeCategory::commonMain([], TYPE_CATEGORY_IT, 'it'))->toBeNull();
    });

    test('a main category missing from the list gives none', function (): void {
        expect(TypeCategory::commonMain([UtilityType::Electricity, UtilityType::Gas], array_slice(TYPE_CATEGORY_IT, 1), 'it'))->toBeNull();
    });
});
