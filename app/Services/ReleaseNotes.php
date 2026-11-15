<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ReleaseNoteData;
use JsonException;

/**
 * Reads the simple notes of the versions of the app (`resources/release/notes.json`, written at each
 * release, newest first) for the panel *What's new*.
 */
final readonly class ReleaseNotes
{
    /** The languages tried, after the one asked, when a version has no notes in it. */
    private const array FALLBACK_LOCALES = ['en', 'it'];

    /**
     * @param  string  $path  the JSON file with the notes
     */
    public function __construct(private string $path) {}

    /**
     * The versions with their notes, newest first. A missing or broken file gives none, and an entry
     * without version, date or notes is left out, so the panel never breaks on a bad release.
     *
     * @param  string  $locale  the language of the person (`it`, `en`)
     * @return list<ReleaseNoteData>
     */
    public function latest(string $locale): array
    {
        $notes = [];

        foreach ($this->entries() as $entry) {
            $note = $this->note($entry, $locale);

            if ($note instanceof ReleaseNoteData) {
                $notes[] = $note;
            }
        }

        return $notes;
    }

    /**
     * The entries of the file, or none when it is missing or not a JSON list.
     *
     * @return list<mixed>
     */
    private function entries(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $content = file_get_contents($this->path);

        try {
            $entries = $content === false ? null : json_decode($content, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($entries) && array_is_list($entries) ? $entries : [];
    }

    /**
     * One entry in the language asked, or in the first other language that has notes.
     *
     * @param  mixed  $entry  an entry of the file
     * @param  string  $locale  the language of the person
     */
    private function note(mixed $entry, string $locale): ?ReleaseNoteData
    {
        if (! is_array($entry) || ! is_string($entry['version'] ?? null) || ! is_string($entry['date'] ?? null) || ! is_array($entry['notes'] ?? null)) {
            return null;
        }

        foreach (array_unique([$locale, ...self::FALLBACK_LOCALES]) as $language) {
            $lines = $this->lines($entry['notes'][$language] ?? null);

            if ($lines !== []) {
                return new ReleaseNoteData($entry['version'], $entry['date'], $lines);
            }
        }

        return null;
    }

    /**
     * The sentences of one language, without what is not a sentence.
     *
     * @param  mixed  $lines  the notes of one language
     * @return list<string>
     */
    private function lines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        return array_values(array_filter($lines, static fn (mixed $line): bool => is_string($line) && trim($line) !== ''));
    }
}
