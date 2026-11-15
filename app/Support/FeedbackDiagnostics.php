<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Cleans the details the browser sends with a report of a problem: only the known keys stay, every list is cut to
 * its limit (keeping the last entries) and every string is cut, so nothing unexpected or huge is saved.
 */
final class FeedbackDiagnostics
{
    /** The most entries kept of each list. */
    public const int MAX_PAGES = 10;

    public const int MAX_CONSOLE = 50;

    public const int MAX_REQUESTS = 20;

    /**
     * The details as they are saved.
     *
     * @param  array<mixed>  $input  what the browser sent, decoded from JSON
     * @param  string  $serverVersion  the version of the app that received it
     * @return array{
     *     client_build: ?string,
     *     server_version: string,
     *     viewport: array{width: ?int, height: ?int, dpr: ?float, orientation: ?string},
     *     phone: ?bool, ios: ?bool, standalone: ?bool, theme: ?string, locale: ?string, online: ?bool,
     *     connection: ?string, page_age_seconds: ?int, component: ?string,
     *     pages: list<array{at: string, path: string, component: ?string}>,
     *     console: list<array{at: string, level: string, message: string, component: ?string}>,
     *     requests: list<array{at: string, method: string, path: string, status: ?int, request_id: ?string}>
     * }
     */
    public static function normalize(array $input, string $serverVersion): array
    {
        $viewport = is_array($input['viewport'] ?? null) ? $input['viewport'] : [];

        return [
            'client_build' => self::text($input['client_build'] ?? null, 100),
            'server_version' => Str::limit($serverVersion, 100, ''),
            'viewport' => [
                'width' => self::int($viewport['width'] ?? null),
                'height' => self::int($viewport['height'] ?? null),
                'dpr' => is_int($viewport['dpr'] ?? null) || is_float($viewport['dpr'] ?? null) ? (float) $viewport['dpr'] : null,
                'orientation' => self::text($viewport['orientation'] ?? null, 30),
            ],
            'phone' => self::bool($input['phone'] ?? null),
            'ios' => self::bool($input['ios'] ?? null),
            'standalone' => self::bool($input['standalone'] ?? null),
            'theme' => self::text($input['theme'] ?? null, 30),
            'locale' => self::text($input['locale'] ?? null, 30),
            'online' => self::bool($input['online'] ?? null),
            'connection' => self::text($input['connection'] ?? null, 30),
            'page_age_seconds' => self::int($input['page_age_seconds'] ?? null),
            'component' => self::text($input['component'] ?? null, 200),
            'pages' => self::pages($input['pages'] ?? null),
            'console' => self::console($input['console'] ?? null),
            'requests' => self::requests($input['requests'] ?? null),
        ];
    }

    /**
     * The last visited pages.
     *
     * @return list<array{at: string, path: string, component: ?string}>
     */
    private static function pages(mixed $list): array
    {
        $pages = [];
        foreach (self::last($list, self::MAX_PAGES) as $entry) {
            $pages[] = [
                'at' => self::text($entry['at'] ?? null, 40) ?? '',
                'path' => self::text($entry['path'] ?? null, 2048) ?? '',
                'component' => self::text($entry['component'] ?? null, 200),
            ];
        }

        return $pages;
    }

    /**
     * The last errors and warnings of the browser console; other levels are dropped.
     *
     * @return list<array{at: string, level: string, message: string, component: ?string}>
     */
    private static function console(mixed $list): array
    {
        $console = [];
        foreach (self::last($list, null) as $entry) {
            $level = $entry['level'] ?? null;
            if ($level !== 'error' && $level !== 'warn') {
                continue;
            }
            $console[] = [
                'at' => self::text($entry['at'] ?? null, 40) ?? '',
                'level' => $level,
                'message' => self::text($entry['message'] ?? null, 500) ?? '',
                'component' => self::text($entry['component'] ?? null, 200),
            ];
        }

        return array_slice($console, -self::MAX_CONSOLE);
    }

    /**
     * The last requests the page made that failed.
     *
     * @return list<array{at: string, method: string, path: string, status: ?int, request_id: ?string}>
     */
    private static function requests(mixed $list): array
    {
        $requests = [];
        foreach (self::last($list, self::MAX_REQUESTS) as $entry) {
            $requests[] = [
                'at' => self::text($entry['at'] ?? null, 40) ?? '',
                'method' => self::text($entry['method'] ?? null, 10) ?? '',
                'path' => self::text($entry['path'] ?? null, 2048) ?? '',
                'status' => self::int($entry['status'] ?? null),
                'request_id' => self::text($entry['request_id'] ?? null, 100),
            ];
        }

        return $requests;
    }

    /**
     * The last entries of a list that are themselves arrays.
     *
     * @return list<array<mixed>>
     */
    private static function last(mixed $list, ?int $limit): array
    {
        if (! is_array($list)) {
            return [];
        }

        $entries = array_values(array_filter($list, is_array(...)));

        return $limit === null ? $entries : array_slice($entries, -$limit);
    }

    /**
     * A string cut to its length, or null when it is not a string.
     */
    private static function text(mixed $value, int $max): ?string
    {
        return is_string($value) ? mb_substr($value, 0, $max) : null;
    }

    /**
     * An integer, or null when it is not one.
     */
    private static function int(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * A boolean, or null when it is not one.
     */
    private static function bool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
