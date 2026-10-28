<?php

declare(strict_types=1);

use App\Models\Vehicle;
use App\Support\ModelId;

test('the id of a saved record is its number', function (): void {
    expect(ModelId::of((new Vehicle)->forceFill(['id' => 21])))->toBe(21);
});

test('a record without a number has no id', function (): void {
    ModelId::of(new Vehicle);
})->throws(InvalidArgumentException::class, 'App\Models\Vehicle has no numeric id.');
