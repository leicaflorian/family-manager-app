<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The check on the corners of a sheet that the model found in a photo: an answer of a model is not trusted as it is.
 */
final class Corners
{
    /** A point this far outside the photo (a part of its side) is brought back to the edge; farther, the answer is not believed. */
    private const float OVERSHOOT = 0.03;

    /** A sheet takes at least this part of the photo: anything smaller is not the document. */
    private const float MIN_AREA = 0.1;

    /** Below this a turn counts as a straight line. */
    private const float EPSILON = 1e-6;

    /**
     * The corners as the page can use them, or null ("not found"): four points inside the photo, clockwise from the
     * top left one, that make a quadrilateral with no crossed sides and no dent, at least a tenth of the photo.
     * The order the model gave is not trusted: the points are put in order around their centre.
     *
     * @param  array<array-key, array{x: float, y: float}>  $points  the points, each from 0 to 1 (up to 3% outside)
     * @return list<array{x: float, y: float}>|null
     */
    public static function valid(array $points): ?array
    {
        if (count($points) !== 4) {
            return null;
        }

        $clamped = [];

        foreach ($points as $point) {
            $x = $point['x'];
            $y = $point['y'];

            if (! is_finite($x) || ! is_finite($y)
                || $x < -self::OVERSHOOT || $x > 1 + self::OVERSHOOT
                || $y < -self::OVERSHOOT || $y > 1 + self::OVERSHOOT) {
                return null;
            }

            $clamped[] = ['x' => min(1.0, max(0.0, $x)), 'y' => min(1.0, max(0.0, $y))];
        }

        $ordered = self::clockwiseFromTopLeft($clamped);

        // every corner turns the same way: no dent, no crossed sides, no three points in a line
        for ($i = 0; $i < 4; $i++) {
            if (self::cross($ordered[$i], $ordered[($i + 1) % 4], $ordered[($i + 2) % 4]) <= self::EPSILON) {
                return null;
            }
        }

        $area = (self::cross($ordered[0], $ordered[1], $ordered[2]) + self::cross($ordered[0], $ordered[2], $ordered[3])) / 2;

        return $area >= self::MIN_AREA ? $ordered : null;
    }

    /**
     * The four points in order around their centre, starting from the one nearest to the top left. With y growing
     * downwards, a growing angle goes clockwise on the screen.
     *
     * @param  list<array{x: float, y: float}>  $points  the four points
     * @return list<array{x: float, y: float}>
     */
    private static function clockwiseFromTopLeft(array $points): array
    {
        $centreX = array_sum(array_column($points, 'x')) / 4;
        $centreY = array_sum(array_column($points, 'y')) / 4;

        usort($points, static fn (array $a, array $b): int => atan2($a['y'] - $centreY, $a['x'] - $centreX) <=> atan2($b['y'] - $centreY, $b['x'] - $centreX));

        $first = 0;

        foreach ($points as $index => $point) {
            if ($points[$first]['x'] + $points[$first]['y'] > $point['x'] + $point['y']) {
                $first = $index;
            }
        }

        return array_map(static fn (int $step): array => $points[($first + $step) % 4], [0, 1, 2, 3]);
    }

    /**
     * Twice the area of the triangle a-b-c: positive when c is on the right going from a to b (y grows downwards).
     *
     * @param  array{x: float, y: float}  $a  the first point
     * @param  array{x: float, y: float}  $b  the second point
     * @param  array{x: float, y: float}  $c  the third point
     */
    private static function cross(array $a, array $b, array $c): float
    {
        return ($b['x'] - $a['x']) * ($c['y'] - $a['y']) - ($b['y'] - $a['y']) * ($c['x'] - $a['x']);
    }
}
