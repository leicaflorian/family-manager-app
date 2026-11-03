<?php

declare(strict_types=1);

namespace App\Actions\Reading;

use App\Actions\Documents\FileDocument;
use App\Models\File;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Files many waiting files as documents, one after the other, each in its own transaction: a file that does
 * not save leaves the others saved, and the reason is kept for its row.
 */
final readonly class ImportFiles
{
    /**
     * @param  FileDocument  $fileDocument  files one file
     */
    public function __construct(private FileDocument $fileDocument) {}

    /**
     * Files each file as a document.
     *
     * @param  list<array{file_id: int, data: array{fields: array<string, mixed>, links: list<array{type: string, id: int}>}}>  $entries  the files with their documents
     * @return array<int, string> why each file that was not filed failed, by file id, in the language of the request
     */
    public function handle(array $entries): array
    {
        $failures = [];

        foreach ($entries as $entry) {
            try {
                if (! $this->file($entry['file_id'], $entry['data'])) {
                    $failures[$entry['file_id']] = __('This file was already filed.');
                }
            } catch (Throwable $exception) {
                report($exception);
                $failures[$entry['file_id']] = __('This file could not be saved.');
            }
        }

        return $failures;
    }

    /**
     * Files one file, if it still waits: the row is locked first, so two imports of the same file make one
     * document.
     *
     * @param  int  $id  the file
     * @param  array{fields: array<string, mixed>, links: list<array{type: string, id: int}>}  $data  its document
     * @return bool false when the file no longer waits
     *
     * @throws Throwable
     */
    private function file(int $id, array $data): bool
    {
        return DB::transaction(function () use ($id, $data): bool {
            $file = File::query()->toSort()->lockForUpdate()->find($id);

            if (! $file instanceof File) {
                return false;
            }

            $this->fileDocument->handle($file, $data);

            return true;
        });
    }
}
