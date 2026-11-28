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

test('an id sent by a form is any positive number, a JSON float too', function (mixed $value, ?int $id): void {
    expect(ModelId::sent($value))->toBe($id);
})->with([
    'int' => [12, 12],
    'digits' => ['12', 12],
    'float' => [12.0, 12],
    'zero' => [0, null],
    'negative' => ['-3', null],
    'text' => ['abc', null],
    'null' => [null, null],
]);
