<?php

declare(strict_types=1);

namespace App\Copy;

/**
 * What a copy from Directus did: the rows per table, what it left out and how
 * long it took.
 */
final readonly class CopyReport
{
    /**
     * @param  array<string, int>  $rows  the rows copied, by Directus table
     * @param  array<string, list<string>>  $ignoredColumns  the Directus fields the app has no column for, by Directus table
     * @param  list<string>  $missingFiles  the files whose content is not in the uploads: the row is there, the content is not
     * @param  array<string, int>  $leftOut  the rows left out because a row they need is missing (a document without its file, a link to a row not copied), by Directus table
     * @param  list<string>  $withoutAccount  the people whose Directus account has no email: they are copied without an account
     * @param  float  $seconds  how long it took
     */
    public function __construct(
        public array $rows,
        public array $ignoredColumns,
        public array $missingFiles,
        public array $leftOut,
        public array $withoutAccount,
        public float $seconds,
    ) {}
}
