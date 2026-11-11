<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Models\File;
use App\Services\FileStore;
use Illuminate\Support\Facades\DB;

/**
 * Deletes files that wait for their document (the row, the file on the disk and its preview), all or none.
 * The permission is checked by the caller.
 */
final readonly class DeletePendingFiles
{
    public function __construct(private FileStore $store) {}

    /**
     * Deletes the files in one transaction.
     *
     * @param  iterable<File>  $files  the files to delete
     * @return int how many files were deleted
     */
    public function handle(iterable $files): int
    {
        return DB::transaction(function () use ($files): int {
            $deleted = 0;

            foreach ($files as $file) {
                $this->store->delete($file);
                $deleted++;
            }

            return $deleted;
        });
    }
}
