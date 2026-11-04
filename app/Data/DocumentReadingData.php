<?php

declare(strict_types=1);

namespace App\Data;

use App\Data\Reading\DocumentProposalData;
use App\Enums\ReadingStatus;
use App\Models\File;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where the reading of the file of a saved document stands, for *Edit a document*: its state, why it failed,
 * what it proposes once done and whether the person can ask for it.
 */
#[TypeScript]
final class DocumentReadingData extends Data
{
    /**
     * @param  int  $file_id  the id of the file, where *Fill in from the file* is sent
     * @param  ReadingStatus|null  $status  where the reading stands, null when it was never asked (a document copied from Directus)
     * @param  string|null  $error  why the file is not read, in the language of whoever asked
     * @param  DocumentProposalData|null  $proposal  what the reading proposes, only once it is done
     * @param  bool  $can_read  whether the button *Fill in from the file* is shown: allowed, the reading is on and the month is not used up
     * @param  string|null  $unavailable  why the button is missing, when the person may read but the reading is off or the limit is reached
     */
    public function __construct(
        public readonly int $file_id,
        public readonly ?ReadingStatus $status,
        public readonly ?string $error,
        public readonly ?DocumentProposalData $proposal,
        public readonly bool $can_read,
        public readonly ?string $unavailable,
    ) {}

    /**
     * The state of the reading of a file.
     *
     * @param  File  $file  the file of the document
     * @param  bool  $allowed  whether the person may read it (`FilePolicy::read`)
     * @param  string|null  $unavailable  why a reading cannot be asked now, null when it can
     */
    public static function fromFile(File $file, bool $allowed, ?string $unavailable): self
    {
        return new self(
            file_id: $file->id,
            status: $file->reading_status,
            error: $file->reading_error,
            proposal: DocumentProposalData::fromFile($file),
            can_read: $allowed && $unavailable === null,
            unavailable: $allowed ? $unavailable : null,
        );
    }
}
