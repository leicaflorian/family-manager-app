<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileSource;
use App\Models\File;
use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
     * @param  FileSource  $source  where the file came from
     * @param  string|null  $note  how it arrived, for *To sort* and for the reading
     * @param  array{received_mail_id?: int, mail_key?: string}  $mail  the mail a file came from, and its key against a second delivery
     * @return File the row of the saved file, with the SHA-256 of its content to find its copies
     *
     * @throws RuntimeException if the family is missing or the disk does not write
     * @throws Throwable if the row is not written: the file just written is removed from the disk first
     */
    public function store(UploadedFile $file, bool $toSort = false, ?array $link = null, FileSource $source = FileSource::Upload, ?string $note = null, array $mail = []): File
    {
        $family = CurrentFamily::id() ?? throw new RuntimeException('No family to save the file for.');
        $checksum = $this->checksum($file->getPathname());

        $path = $file->storeAs($this->folder($family), $this->newName($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? '')), self::DISK)
            ?: throw new RuntimeException('The file was not saved.');

        try {
            return File::query()->create([
                ...$mail,
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize() ?: 0,
                'to_sort' => $toSort,
                'link_type' => $link['type'] ?? null,
                'link_id' => $link['id'] ?? null,
                'source' => $source,
                'note' => $note,
                'checksum' => $checksum,
            ]);
        } catch (Throwable $error) {
            // without its row nobody would ever find the file again
            Storage::disk(self::DISK)->delete($path);

            throw $error;
        }
    }

    /**
     * Saves a file that is not an upload (an attachment of a mail) as `store()` does: same folder, checksum and row.
     *
     * @param  string  $path  the file on the local disk of PHP
     * @param  string  $name  the name it is downloaded with, whose extension the stored file takes
     * @param  string  $mime  the type it declares
     * @param  bool  $toSort  true for a file that waits in *To sort*
     * @param  FileSource  $source  where the file came from
     * @param  string|null  $note  how it arrived
     * @param  array{received_mail_id?: int, mail_key?: string}  $mail  the mail it came from, and its key
     * @return File the row of the saved file
     *
     * @throws RuntimeException if the family is missing or the disk does not write
     * @throws Throwable if the row is not written
     */
    public function storePath(string $path, string $name, string $mime, bool $toSort, FileSource $source, ?string $note, array $mail = []): File
    {
        // a local file, not an upload of this request: test mode skips the check of PHP that it was uploaded
        return $this->store(new UploadedFile($path, $name, $mime, null, true), $toSort, null, $source, $note, $mail);
    }

    /**
     * Saves a text made by the app (the notice of a mail without attachments) as a file: it is written to a
     * temporary file and stored as an upload, so checksum, preview and folder are those of every other file.
     *
     * @param  string  $contents  the bytes of the file
     * @param  string  $name  the name it is downloaded with
     * @param  string  $mime  its type
     * @param  bool  $toSort  true for a file that waits in *To sort*
     * @param  FileSource  $source  where the file came from
     * @param  string|null  $note  how it arrived
     * @param  array{received_mail_id?: int, mail_key?: string}  $mail  the mail it came from, and its key
     * @return File the row of the saved file
     *
     * @throws RuntimeException if the temporary file is not written, the family is missing or the disk does not write
     * @throws Throwable if the row is not written
     */
    public function storeContents(string $contents, string $name, string $mime, bool $toSort, FileSource $source, ?string $note, array $mail = []): File
    {
        $temporary = tempnam(sys_get_temp_dir(), 'fm-file-') ?: throw new RuntimeException('No temporary file for the contents.');

        try {
            if (file_put_contents($temporary, $contents) === false) {
                throw new RuntimeException('The temporary file was not written.');
            }

            return $this->storePath($temporary, $name, $mime, $toSort, $source, $note, $mail);
        } finally {
            Filesystem::delete($temporary);
        }
    }

    /**
     * The SHA-256 of a file's content, which finds the copies of a file.
     *
     * @param  string  $path  the file on a local disk
     * @return string|null the hash, null when the file does not read
     */
    public function checksum(string $path): ?string
    {
        return @hash_file('sha256', $path) ?: null;
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
     * Deletes a file: the row, then the file on the disk and its preview, if any. The disk is touched only once
     * the row is gone for good: inside a transaction that fails, the file stays with its row.
     *
     * @param  File  $file  the file to delete
     */
    public function delete(File $file): void
    {
        DB::transaction(function () use ($file): void {
            $file->delete();
            DB::afterCommit(static function () use ($file): void {
                Storage::disk($file->disk)->delete($file->path);
                resolve(FilePreviews::class)->deleteOf($file);
            });
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
