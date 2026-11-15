<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads the version of the app from the VERSION file at the root of the repository.
 */
final class AppVersion
{
    /**
     * The pattern of a version number: `X.Y.Z`.
     */
    public const string PATTERN = '/^\d+\.\d+\.\d+$/';

    /**
     * The version written in the file, or `dev` when the file is missing or does not hold an `X.Y.Z` version.
     *
     * @param  string  $path  Absolute path of the VERSION file.
     */
    public static function read(string $path): string
    {
        if (! is_file($path)) {
            return 'dev';
        }

        $version = trim((string) file_get_contents($path));

        return preg_match(self::PATTERN, $version) === 1 ? $version : 'dev';
    }
}
