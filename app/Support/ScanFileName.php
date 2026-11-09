<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FileSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The names of the files that come from *Scan*: «Telefono 2026-10-25 19.04.pdf» for the phone, «Scanner …» for the
 * scanner, with the day and the time of Rome, or the name written for the scanner. A name already taken takes «(2)».
 */
final class ScanFileName
{
    /**
     * The name of one file.
     *
     * @param  FileSource  $source  where the file comes from: the phone or the scanner
     * @param  CarbonImmutable  $at  the moment of the upload, in any zone
     * @param  string  $extension  the extension of the file, without the dot
     * @param  Collection<int, string>  $taken  the names the family already has
     * @param  string  $written  the name written by the person (scanner only), may be empty
     * @return string the name, free among `$taken`
     */
    public static function for(FileSource $source, CarbonImmutable $at, string $extension, Collection $taken, string $written = ''): string
    {
        $base = self::clean($written) ?: sprintf(
            '%s %s',
            $source === FileSource::Scanner ? 'Scanner' : 'Telefono',
            $at->setTimezone('Europe/Rome')->format('Y-m-d H.i'),
        );

        $name = sprintf('%s.%s', $base, $extension);

        for ($number = 2; $taken->contains($name); $number++) {
            $name = sprintf('%s (%d).%s', $base, $number, $extension);
        }

        return $name;
    }

    /**
     * The name written, without a final `.pdf`, the signs a file name cannot hold and control characters, with single spaces.
     *
     * @param  string  $written  the name as written
     */
    private static function clean(string $written): string
    {
        $name = preg_replace(['/\.pdf$/i', '/[\\\\\/:*?"<>|]+/', '/\p{C}+/u', '/\s+/'], ['', ' ', ' ', ' '], $written);

        return trim((string) $name);
    }
}
