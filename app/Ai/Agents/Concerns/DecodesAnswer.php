<?php

declare(strict_types=1);

namespace App\Ai\Agents\Concerns;

use Throwable;

/**
 * Reads the answer of a model as an array: the structured answer as it is, or its text decoded as JSON.
 */
trait DecodesAnswer
{
    /**
     * The answer as an array, or null when it is not one (no answer, or a text that is not a JSON object).
     *
     * @param  array<array-key, mixed>|string|null  $answer  the structured answer, or its text
     * @return array<array-key, mixed>|null
     */
    private static function decodeAnswer(array|string|null $answer): ?array
    {
        if (! is_string($answer)) {
            return $answer;
        }

        try {
            $decoded = json_decode($answer, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
