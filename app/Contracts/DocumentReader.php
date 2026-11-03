<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Reading\Reading;
use App\Exceptions\Reading\ReadingFailed;
use App\Services\Reading\ReadingContext;
use App\Services\Reading\ReadingSource;

/**
 * Reads a document with an AI service: what is on the paper, as the fields of an extraction.
 */
interface DocumentReader
{
    /**
     * Whether the reading is on: a service is chosen and has its key (or, for a model of one's own, its address).
     */
    public function isActive(): bool;

    /**
     * Reads a file.
     *
     * @param  ReadingSource  $source  the file as the model reads it
     * @param  ReadingContext  $context  the lists the model picks from and the notes of the file
     * @param  string  $locale  the language of who uploaded the file (`it` or `en`)
     *
     * @throws ReadingFailed when the service gives no extraction
     */
    public function read(ReadingSource $source, ReadingContext $context, string $locale): Reading;
}
