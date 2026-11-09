<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasReaderOptions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Throwable;

/**
 * The model that finds the sheet of paper in a photo, so that the photo can be cropped to it. It carries the system
 * text, the request and the reading of the answer; whether the corners make a sheet is checked by `Corners`.
 */
#[Timeout(30)]
final class CornersAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use HasReaderOptions;
    use Promptable;

    /** The models are asked for whole numbers on this scale (the one they are best at), then brought to 0-1. */
    public const int SCALE = 1000;

    /** The corners in the order the model gives them, clockwise from the top left one. */
    public const array NAMES = ['top_left', 'top_right', 'bottom_right', 'bottom_left'];

    /** What the model is for and how it gives the corners. */
    public const string SYSTEM = <<<'TXT'
You locate a sheet of paper in a photo taken with a phone, so that the photo can be cropped to the sheet.

The photo shows a paper document (a letter, a bill, a receipt, a form, a card) lying on a surface or held in a hand. Find the four corners of the paper itself: the physical edges of the sheet against the background, not the block of printed text and not the table.

Coordinates are integers from 0 to 1000: x goes from 0 (left edge of the photo) to 1000 (right edge), y from 0 (top edge) to 1000 (bottom edge).

Rules:
- top_left, top_right, bottom_right, bottom_left are the corners of the sheet as they appear in the photo, going clockwise from the one nearest to the top left of the photo.
- Be precise: each point must sit exactly on the tip of the corner of the paper.
- If a corner is slightly outside the photo or covered, give the nearest point inside the photo to where it would be.
- found is false when there is no sheet of paper in the photo, or when the sheet fills the whole photo and none of its edges is visible. In that case give 0 for every coordinate.
TXT;

    /** The text that goes with the photo. */
    public const string REQUEST = 'Find the four corners of the sheet of paper in this photo.';

    /**
     * @param  string  $model  the model asked, whose options depend on it
     */
    public function __construct(private readonly string $model) {}

    /**
     * What the model is for and how it gives the corners.
     */
    public function instructions(): string
    {
        return self::SYSTEM;
    }

    /**
     * The schema of the answer: whether a sheet was found and its four corners, all required.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $answer = ['found' => $schema->boolean()->required()];

        foreach (self::NAMES as $name) {
            $answer[$name] = $schema->object([
                'x' => $schema->integer()->required(),
                'y' => $schema->integer()->required(),
            ])->withoutAdditionalProperties()->required();
        }

        return $answer;
    }

    /**
     * The answer of the model as corners from 0 to 1, or null when it found no sheet or the answer is broken.
     * Whether the corners make a sheet is for `Corners::valid()`.
     *
     * @param  array<array-key, mixed>|string|null  $answer  the structured answer, or its text
     * @return list<array{x: float, y: float}>|null
     */
    public static function parse(array|string|null $answer): ?array
    {
        if (is_string($answer)) {
            try {
                $decoded = json_decode($answer, true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return null;
            }

            $answer = is_array($decoded) ? $decoded : null;
        }

        if (($answer['found'] ?? null) !== true) {
            return null;
        }

        $corners = [];

        foreach (self::NAMES as $name) {
            $point = $answer[$name] ?? null;

            if (! is_array($point) || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) {
                return null;
            }

            $corners[] = ['x' => (float) $point['x'] / self::SCALE, 'y' => (float) $point['y'] / self::SCALE];
        }

        return $corners;
    }
}
