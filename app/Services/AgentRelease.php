<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The agent of the home scanner the page *Scan the sheets* offers: the last version its repository published that the
 * page can talk to. Nothing of the agent is in this app: the version is asked to GitHub when it is needed, and the
 * download is a redirect to the zip of that release, so publishing the agent is enough for the page to offer it within
 * the hour.
 */
final class AgentRelease
{
    /** The releases of the repository of the agent: each version is one, with its zips and `version.json`. */
    public const string RELEASES = 'https://github.com/leicaflorian/family-manager-agent/releases';

    /** The first number of the versions the page can talk to: a higher one is not offered. */
    public const int MAJOR = 1;

    /** The systems the agent is built for. */
    public const array SYSTEMS = ['mac', 'windows'];

    /** The list of the releases, from the API of GitHub. */
    private const string LIST = 'https://api.github.com/repos/leicaflorian/family-manager-agent/releases';

    private const string CACHE_KEY = 'agent-release';

    /** The last version GitHub told, kept without an end: offered while GitHub does not answer. */
    private const string LAST_KNOWN_KEY = 'agent-release-last-known';

    /** Seconds the answer of GitHub is kept, and how long a missing answer is left alone before asking again. */
    private const int KEEP = 3600;

    private const int RETRY = 300;

    /** Seconds GitHub has to answer each call. */
    private const int TIMEOUT = 10;

    /**
     * The last version published with the first number the page can use, as it is kept for an hour. When GitHub
     * does not answer, the version known before is offered (nothing, the first time) and it is asked again after five
     * minutes.
     *
     * @return array{version: string, files: array<string, string>}|null the version and the zip of each system, or null
     */
    public function latest(): ?array
    {
        /** @var array{release: array{version: string, files: array<string, string>}|null}|null $kept */
        $kept = Cache::get(self::CACHE_KEY);

        if ($kept === null) {
            $release = $this->ask();

            if ($release !== null) {
                Cache::forever(self::LAST_KNOWN_KEY, $release);
            }

            /** @var array{version: string, files: array<string, string>}|null $known */
            $known = $release ?? Cache::get(self::LAST_KNOWN_KEY);
            $kept = ['release' => $known];
            Cache::put(self::CACHE_KEY, $kept, $release === null ? self::RETRY : self::KEEP);
        }

        return $kept['release'];
    }

    /**
     * Where the zip of a system is, or null when no release is known or it has no zip for that system.
     *
     * @param  string  $system  `mac` or `windows`
     */
    public function download(string $system): ?string
    {
        $release = $this->latest();
        $name = $release['files'][$system] ?? null;

        return $release === null || $name === null
            ? null
            : self::RELEASES."/download/v{$release['version']}/{$name}";
    }

    /**
     * Asks GitHub for the last usable version and its `version.json`.
     *
     * @return array{version: string, files: array<string, string>}|null
     */
    private function ask(): ?array
    {
        try {
            $list = Http::timeout(self::TIMEOUT)->acceptJson()->get(self::LIST, ['per_page' => 30]);

            if (! $list->successful()) {
                return null;
            }

            $version = $this->newestUsable($list->json());

            if ($version === null) {
                return null;
            }

            $file = Http::timeout(self::TIMEOUT)->acceptJson()->get(self::RELEASES."/download/v{$version}/version.json");

            return $file->successful() ? $this->parse($file->json(), $version) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The highest version among the releases that are neither a draft nor a pre-release, with a first number the
     * page can talk to.
     *
     * @param  mixed  $releases  the list as GitHub sent it
     */
    private function newestUsable(mixed $releases): ?string
    {
        $newest = null;

        foreach (is_array($releases) ? $releases : [] as $release) {
            if (! is_array($release) || ($release['draft'] ?? false) !== false || ($release['prerelease'] ?? false) !== false) {
                continue;
            }

            $tag = $release['tag_name'] ?? null;

            if (! is_string($tag) || preg_match('/^v(\d+\.\d+\.\d+)$/', $tag, $found) !== 1) {
                continue;
            }

            if ((int) $found[1] <= self::MAJOR && ($newest === null || version_compare($found[1], $newest, '>'))) {
                $newest = $found[1];
            }
        }

        return $newest;
    }

    /**
     * Reads `version.json` as it arrived. It comes from the network and ends in an address: anything that is not
     * the version asked for and names of zips is refused.
     *
     * @param  mixed  $body  the file as GitHub sent it
     * @return array{version: string, files: array<string, string>}|null
     */
    private function parse(mixed $body, string $expected): ?array
    {
        if (! is_array($body) || ($body['version'] ?? null) !== $expected || ! is_array($body['files'] ?? null)) {
            return null;
        }

        $files = [];

        foreach ($body['files'] as $system => $name) {
            if (! is_string($name) || preg_match('/^[\w.-]+\.zip$/', $name) !== 1) {
                return null;
            }

            $files[(string) $system] = $name;
        }

        return ['version' => $expected, 'files' => $files];
    }
}
