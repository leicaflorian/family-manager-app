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
    public const string DISK = 'local';

    /**
     * Saves a file in the folder of the current family and creates its row.
     *
     * @param  UploadedFile  $file  the uploaded file
     * @param  bool  $toSort  true for a file of *Upload*, which waits for its document
     * @param  array{type: string, id: int}|null  $link  the card a file of *Upload* comes from, from `PresetLink`
     * @return File the row of the saved file
     *
     * @throws RuntimeException if the family is missing or the disk does not write
     */
    public function store(UploadedFile $file, bool $toSort = false, ?array $link = null): File
    {
        $family = CurrentFamily::id() ?? throw new RuntimeException('No family to save the file for.');

        $path = $file->storeAs($this->folder($family), $this->newName($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? '')), self::DISK)
            ?: throw new RuntimeException('The file was not saved.');

        return File::query()->create([
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'to_sort' => $toSort,
            'link_type' => $link['type'] ?? null,
            'link_id' => $link['id'] ?? null,
        ]);
    }

    /**
     * Copies a file from outside the app into the folder of a family, for the
     * copy from Directus. The row is written by whoever copies.
     *
     * @param  int  $family  the family the file belongs to
     * @param  string|null  $source  the file to copy; null leaves the path without content
     * @param  string  $originalName  the name it had, for its extension
     * @return string the path of the file on the disk
     *
     * @throws RuntimeException if the source does not open or the disk does not write
     */
    public function import(int $family, ?string $source, string $originalName): string
    {
        $path = $this->folder($family).'/'.$this->newName(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($source !== null) {
            $stream = fopen($source, 'rb') ?: throw new RuntimeException("The file {$source} does not open.");

            try {
                Storage::disk(self::DISK)->writeStream($path, $stream) ?: throw new RuntimeException('The file was not saved.');
            } finally {
                fclose($stream);
            }
        }

        return $path;
    }

    /**
     * Deletes the folder of a family, with all its files.
     *
     * @param  int  $family  the family whose files go
     */
    public function deleteFamily(int $family): void
    {
        Storage::disk(self::DISK)->deleteDirectory($this->folder($family));
    }

    /**
     * Deletes a file: the row, the file on the disk and its preview, if any.
     *
     * @param  File  $file  the file to delete
     */
    public function delete(File $file): void
    {
        DB::transaction(function () use ($file): void {
            $file->delete();
            Storage::disk($file->disk)->delete($file->path);
            resolve(FilePreviews::class)->deleteOf($file);
        });
    }

    /**
     * The folder of a family on the disk.
     *
     * @param  int  $family  the family
     */
    private function folder(int $family): string
    {
        return "families/{$family}";
    }

    /**
     * A new name for a file, with the extension of the original.
     *
     * @param  string  $extension  the extension, empty if unknown
     */
    private function newName(string $extension): string
    {
        return Str::uuid()->toString().'.'.(Str::lower($extension) ?: 'bin');
    }
}
