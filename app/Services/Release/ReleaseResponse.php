<?php

declare(strict_types=1);

namespace App\Services\Release;

use App\Enums\ReleaseBump;
use JsonException;

/**
 * Claude's answer for a release: which number to raise, why, the changelog text and the notes for the people.
 */
final readonly class ReleaseResponse
{
    private const int MAX_NOTES = 8;

    /**
     * @param  ReleaseBump  $bump  Minor or patch.
     * @param  string  $reason  Why Claude chose that bump.
     * @param  string  $changelog  Markdown of the changelog section.
     * @param  array{it: list<string>, en: list<string>}  $notes  What is new, one text per line, in each language.
     */
    private function __construct(
        public ReleaseBump $bump,
        public string $reason,
        public string $changelog,
        public array $notes,
    ) {}

    /**
     * Reads and checks the JSON answer.
     *
     * @param  string  $json  The answer of Claude.
     *
     * @throws InvalidReleaseResponse When the answer is not JSON or a field is missing or wrong.
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidReleaseResponse('The answer is not valid JSON: '.$e->getMessage(), 0, $e);
        }

        if (! is_array($data)) {
            throw new InvalidReleaseResponse('The answer is not a JSON object.');
        }

        $bump = is_string($data['bump'] ?? null) ? ReleaseBump::tryFrom($data['bump']) : null;

        if (! in_array($bump, [ReleaseBump::Minor, ReleaseBump::Patch], true)) {
            throw new InvalidReleaseResponse('The field "bump" must be "minor" or "patch".');
        }

        $notes = $data['notes'] ?? null;

        return new self(
            $bump,
            self::text($data, 'reason'),
            self::text($data, 'changelog'),
            [
                'it' => self::lines(is_array($notes) ? $notes : [], 'it'),
                'en' => self::lines(is_array($notes) ? $notes : [], 'en'),
            ],
        );
    }

    /**
     * A non-empty string field.
     *
     * @param  array<mixed>  $data  The answer.
     * @param  string  $field  The field name.
     *
     * @throws InvalidReleaseResponse When the field is not a non-empty string.
     */
    private static function text(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidReleaseResponse(sprintf('The field "%s" must be a non-empty text.', $field));
        }

        return $value;
    }

    /**
     * The notes of one language: at least one, each a non-empty string.
     *
     * @param  array<mixed>  $notes  The `notes` object.
     * @param  string  $language  `it` or `en`.
     * @return list<string>
     *
     * @throws InvalidReleaseResponse When the list is missing, empty or has a wrong item.
     */
    private static function lines(array $notes, string $language): array
    {
        $list = $notes[$language] ?? null;

        if (! is_array($list) || $list === [] || ! array_is_list($list)) {
            throw new InvalidReleaseResponse(sprintf('The field "notes.%s" must be a list with at least one text.', $language));
        }

        if (count($list) > self::MAX_NOTES) {
            throw new InvalidReleaseResponse(sprintf('The field "notes.%s" must have at most %d texts.', $language, self::MAX_NOTES));
        }

        $lines = [];

        foreach ($list as $line) {
            if (! is_string($line) || trim($line) === '') {
                throw new InvalidReleaseResponse(sprintf('Every item of "notes.%s" must be a non-empty text.', $language));
            }

            $lines[] = $line;
        }

        return $lines;
    }
}
