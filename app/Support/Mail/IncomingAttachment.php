<?php

declare(strict_types=1);

namespace App\Support\Mail;

/**
 * One file attached to an incoming mail, as Mailgun uploaded it.
 */
final readonly class IncomingAttachment
{
    /**
     * @param  string  $filename  the name the sender gave the file
     * @param  string  $contentType  the type the sender declared
     * @param  int  $size  the size in bytes
     * @param  string  $path  the temporary file PHP made for the upload
     * @param  int  $index  the number of the field `attachment-N`: the same on every delivery of the same mail
     * @param  int  $uploadError  the error of PHP for the upload (`UPLOAD_ERR_OK` when the file arrived whole)
     */
    public function __construct(
        public string $filename,
        public string $contentType,
        public int $size,
        public string $path,
        public int $index,
        public int $uploadError = UPLOAD_ERR_OK,
    ) {}
}
