<?php

declare(strict_types=1);

use App\Ai\Agents\CornersAgent;
use App\Support\Corners;

/**
 * Four points from a list of pairs.
 *
 * @param  list<array{0: float, 1: float}>  $pairs
 * @return list<array{x: float, y: float}>
 */
function cornerPoints(array $pairs): array
{
    return array_map(static fn (array $pair): array => ['x' => $pair[0], 'y' => $pair[1]], $pairs);
}

test('a good sheet comes back as it is', function (): void {
    $sheet = cornerPoints([[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]]);

    expect(Corners::valid($sheet))->toBe($sheet);
});

test('points out of order come back clockwise from the top left', function (): void {
    $shuffled = cornerPoints([[0.9, 0.9], [0.1, 0.1], [0.1, 0.9], [0.9, 0.1]]);

    expect(Corners::valid($shuffled))->toBe(cornerPoints([[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]]));
});

test('a tilted sheet starts from the corner nearest to the top left', function (): void {
    $tilted = cornerPoints([[0.5, 0.05], [0.95, 0.5], [0.5, 0.95], [0.05, 0.5]]);

    expect(Corners::valid(array_reverse($tilted)))->toBe(cornerPoints([[0.5, 0.05], [0.95, 0.5], [0.5, 0.95], [0.05, 0.5]]));
});

test('a point 2% out of the photo is brought back to the edge', function (): void {
    $result = Corners::valid(cornerPoints([[-0.02, 0.1], [0.9, 0.1], [0.9, 1.02], [0.1, 0.9]]));

    expect($result)->toBe(cornerPoints([[0.0, 0.1], [0.9, 0.1], [0.9, 1.0], [0.1, 0.9]]));
});

test('a point 5% out of the photo gives null', function (float $x, float $y): void {
    expect(Corners::valid(cornerPoints([[$x, $y], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]])))->toBeNull();
})->with([[-0.05, 0.1], [1.05, 0.1], [0.1, -0.05], [0.1, 1.05]]);

test('a bow tie is put in order, a crossing that cannot be undone gives null', function (): void {
    // the points are ordered around the centre, so the crossed sides of a bow tie become a plain sheet
    expect(Corners::valid(cornerPoints([[0.1, 0.1], [0.9, 0.9], [0.9, 0.1], [0.1, 0.9]])))->toBe(cornerPoints([[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]]))
        ->and(Corners::valid(cornerPoints([[0.1, 0.1], [0.9, 0.1], [0.5, 0.2], [0.1, 0.9]])))->toBeNull();
});

test('a quadrilateral with a dent gives null', function (): void {
    expect(Corners::valid(cornerPoints([[0.0, 0.0], [1.0, 0.0], [0.5, 0.3], [0.0, 1.0]])))->toBeNull();
});

test('three points in a line give null', function (): void {
    expect(Corners::valid(cornerPoints([[0.1, 0.1], [0.5, 0.1], [0.9, 0.1], [0.1, 0.9]])))->toBeNull();
});

test('a sheet smaller than a tenth of the photo gives null', function (): void {
    expect(Corners::valid(cornerPoints([[0.1, 0.1], [0.4, 0.1], [0.4, 0.4], [0.1, 0.4]])))->toBeNull()
        ->and(Corners::valid(cornerPoints([[0.1, 0.1], [0.5, 0.1], [0.5, 0.5], [0.1, 0.5]])))->not->toBeNull();
});

test('anything but four finite points gives null', function (): void {
    expect(Corners::valid([]))->toBeNull()
        ->and(Corners::valid(cornerPoints([[0.1, 0.1], [0.9, 0.1], [0.9, 0.9]])))->toBeNull()
        ->and(Corners::valid(cornerPoints([[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, NAN]])))->toBeNull()
        ->and(Corners::valid(cornerPoints([[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9], [0.5, 0.5]])))->toBeNull();
});

describe('the answer of the model', function (): void {
    test('whole numbers from 0 to 1000 become points from 0 to 1', function (): void {
        $answer = [
            'found' => true,
            'top_left' => ['x' => 100, 'y' => 120],
            'top_right' => ['x' => 900, 'y' => 100],
            'bottom_right' => ['x' => 880, 'y' => 950],
            'bottom_left' => ['x' => 120, 'y' => 940],
        ];

        expect(CornersAgent::parse($answer))->toBe(cornerPoints([[0.1, 0.12], [0.9, 0.1], [0.88, 0.95], [0.12, 0.94]]))
            ->and(CornersAgent::parse(json_encode($answer)))->toBe(CornersAgent::parse($answer));
    });

    test('not found, broken or incomplete answers give null', function (): void {
        expect(CornersAgent::parse(['found' => false]))->toBeNull()
            ->and(CornersAgent::parse('not json'))->toBeNull()
            ->and(CornersAgent::parse(''))->toBeNull()
            ->and(CornersAgent::parse(null))->toBeNull()
            ->and(CornersAgent::parse(['found' => true, 'top_left' => ['x' => 1, 'y' => 2]]))->toBeNull()
            ->and(CornersAgent::parse(['found' => true, 'top_left' => 'x', 'top_right' => 1, 'bottom_right' => 1, 'bottom_left' => 1]))->toBeNull();
    });
});
