<?php

declare(strict_types=1);

use App\Support\AppVersion;

/**
 * Writes a temporary file with the given content and returns its path.
 */
function versionFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'version');
    file_put_contents($path, $content);

    return $path;
}

it('reads the version of the file', function (): void {
    expect(AppVersion::read(versionFile("2.0.0\n")))->toBe('2.0.0');
});

it('is dev without the file', function (): void {
    expect(AppVersion::read(sys_get_temp_dir().'/missing-version-file'))->toBe('dev');
});

it('is dev for a file that is not a version', function (): void {
    expect(AppVersion::read(versionFile('hello')))->toBe('dev')
        ->and(AppVersion::read(versionFile('')))->toBe('dev');
});
