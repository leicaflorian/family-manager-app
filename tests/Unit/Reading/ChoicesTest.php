<?php

declare(strict_types=1);

use App\Support\Reading\Choices;

describe('pick and label', function (): void {
    $choices = [['id' => 1, 'label' => 'Casa'], ['id' => 2, 'label' => 'Auto']];

    test('pick keeps an id that is among the choices', function () use ($choices): void {
        expect(Choices::pick($choices, 2))->toBe(2);
    });

    test('pick drops an id that is not among the choices', function () use ($choices): void {
        expect(Choices::pick($choices, 9))->toBeNull();
    });

    test('pick drops null', function () use ($choices): void {
        expect(Choices::pick($choices, null))->toBeNull();
    });

    test('label gives the label of the id, null when missing', function () use ($choices): void {
        expect(Choices::label($choices, 2))->toBe('Auto')
            ->and(Choices::label($choices, 9))->toBeNull()
            ->and(Choices::label($choices, null))->toBeNull();
    });
});
