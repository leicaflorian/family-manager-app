<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\File;
use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The file store of a family, on the private `local` disk.
 *
 * Each family has its own folder, `families/<family_id>/`, and each file a new
 * name: the original name stays in the `files` row, for downloading.
 */
final class FileStore
{
    /** The disk of the files: private, read only through the app. */
    private const string DISK = 'local';

    /**
     * Saves a file in the folder of the current family and creates its row.
     *
     * @param  UploadedFile  $file  the uploaded file
     * @param  bool  $toSort  true for a file of *Upload*, which waits for its document
     * @return File the row of the saved file
     *
     * @throws RuntimeException if the family is missing or the disk does not write
     */
    public function store(UploadedFile $file, bool $toSort = false): File
    {
        $family = CurrentFamily::id() ?? throw new RuntimeException('No family to save the file for.');
        $extension = Str::lower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));

        $path = $file->storeAs("families/{$family}", Str::uuid()->toString().'.'.$extension, self::DISK)
            ?: throw new RuntimeException('The file was not saved.');

        return File::query()->create([
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'to_sort' => $toSort,
        ]);
    }

    /**
     * Deletes a file: the row and the file on the disk.
     *
     * @param  File  $file  the file to delete
     */
    public function delete(File $file): void
    {
        DB::transaction(function () use ($file): void {
            $file->delete();
            Storage::disk($file->disk)->delete($file->path);
        });
    }
}
