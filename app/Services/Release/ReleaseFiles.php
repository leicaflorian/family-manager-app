<?php

declare(strict_types=1);

namespace App\Services\Release;

use Carbon\CarbonImmutable;

/**
 * The files a release writes at the root of the repository: VERSION, CHANGELOG.md and resources/release/notes.json.
 */
final readonly class ReleaseFiles
{
    private const string HEADER = "# Changelog\n\nAll notable changes to Family Manager.\n";

    private string $root;

    /**
     * @param  string|null  $root  Absolute path of the repository root; the application base path when null.
     */
    public function __construct(?string $root = null)
    {
        $this->root = $root ?? base_path();
    }

    /**
     * The version in the VERSION file, or null when there is no file yet (a first release).
     *
     * @throws \InvalidArgumentException When the file exists but does not hold an `X.Y.Z` version.
     */
    public function current(): ?Version
    {
        $path = $this->root.'/VERSION';

        if (! is_file($path)) {
            return null;
        }

        return Version::parse(trim((string) file_get_contents($path)));
    }

    /**
     * Absolute path of a file handed to the script that commits and tags: `storage/app/release/<name>`.
     *
     * @param  string  $name  File name, without a directory.
     */
    public function handoffPath(string $name): string
    {
        return $this->root.'/storage/app/release/'.$name;
    }

    /**
     * Writes the three files for a release.
     *
     * @param  Version  $version  The new version.
     * @param  ReleaseResponse  $response  Claude's answer.
     * @param  CarbonImmutable  $date  The day of the release.
     */
    public function write(Version $version, ReleaseResponse $response, CarbonImmutable $date): void
    {
        file_put_contents($this->root.'/VERSION', $version."\n");
        file_put_contents($this->root.'/CHANGELOG.md', $this->changelog($this->section($version, $response, $date)));
        $this->writeNotes($version, $response, $date);
    }

    /**
     * The `## [X.Y.Z] - YYYY-MM-DD` block: the header, a blank line, the changelog and a newline.
     * It goes in CHANGELOG.md and is the message of the tag.
     *
     * @param  Version  $version  The version of the release.
     * @param  ReleaseResponse  $response  Claude's answer.
     * @param  CarbonImmutable  $date  The day of the release.
     */
    public function section(Version $version, ReleaseResponse $response, CarbonImmutable $date): string
    {
        return sprintf("## [%s] - %s\n\n%s\n", $version, $date->format('Y-m-d'), trim($response->changelog));
    }

    /**
     * The text of CHANGELOG.md with the new section before the first older one.
     *
     * @param  string  $section  The new section.
     */
    private function changelog(string $section): string
    {
        $path = $this->root.'/CHANGELOG.md';
        $old = is_file($path) ? (string) file_get_contents($path) : self::HEADER;
        $position = strpos($old, "\n## [");

        if ($position === false) {
            return rtrim($old)."\n\n".$section;
        }

        return substr($old, 0, $position + 1).$section."\n".substr($old, $position + 1);
    }

    /**
     * Puts the entry of the release first in resources/release/notes.json.
     *
     * @param  Version  $version  The version of the release.
     * @param  ReleaseResponse  $response  Claude's answer.
     * @param  CarbonImmutable  $date  The day of the release.
     */
    private function writeNotes(Version $version, ReleaseResponse $response, CarbonImmutable $date): void
    {
        $path = $this->root.'/resources/release/notes.json';
        $entries = [];

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $entries = is_array($decoded) ? $decoded : [];
        }

        array_unshift($entries, [
            'version' => (string) $version,
            'date' => $date->format('Y-m-d'),
            'notes' => $response->notes,
        ]);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }
}
