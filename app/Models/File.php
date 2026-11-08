<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FileSource;
use App\Enums\ReadingStatus;
use App\Services\FilePreviews;
use Carbon\CarbonImmutable;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * An uploaded file (`files`, instead of `directus_files`). The actual file sits on the private disk.
 *
 * @property int $id
 * @property int $family_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property bool $to_sort
 * @property string|null $link_type The type of the card the file was uploaded from, while it waits for its document
 * @property int|null $link_id The id of the card the file was uploaded from, while it waits for its document
 * @property string|null $preview_path The JPEG of the preview, made the first time it is asked
 * @property bool $preview_failed The preview could not be made: it is not tried again
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property ReadingStatus|null $reading_status queued, reading, done, failed, skipped or off; null until the file is queued
 * @property array<array-key, mixed>|null $reading What the AI read from the file
 * @property string|null $reading_error Why the reading failed
 * @property FileSource $source upload, mail, phone or scanner
 * @property string|null $note
 * @property string|null $checksum SHA-256 of the content, to find copies
 * @property int|null $received_mail_id
 * @property string|null $mail_key
 * @property-read Collection<int, AiReading> $aiReadings
 * @property-read int|null $ai_readings_count
 * @property-read Document|null $document
 * @property-read Family $family
 *
 * @method static FileFactory factory($count = null, $state = [])
 * @method static Builder<static>|File newModelQuery()
 * @method static Builder<static>|File newQuery()
 * @method static Builder<static>|File notCopies()
 * @method static Builder<static>|File query()
 * @method static Builder<static>|File toSort()
 *
 * @mixin \Eloquent
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size', 'to_sort', 'link_type', 'link_id', 'source', 'note', 'checksum', 'received_mail_id', 'mail_key'])]
final class File extends FamilyModel
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    /** The older file this one is a copy of, once looked for: false until then. */
    private self|false|null $copyOfFile = false;

    /**
     * The document the file belongs to, once it is filed.
     *
     * @return HasOne<Document, $this>
     */
    public function document(): HasOne
    {
        return $this->hasOne(Document::class);
    }

    /**
     * The AI calls made to read the file; they stay (without the file) when it is deleted.
     *
     * @return HasMany<AiReading, $this>
     */
    public function aiReadings(): HasMany
    {
        return $this->hasMany(AiReading::class);
    }

    /**
     * Whether a preview can be made for the file: its type has one and it did not fail before.
     */
    public function hasPreview(): bool
    {
        return in_array($this->mime_type, FilePreviews::TYPES, true) && ! $this->preview_failed;
    }

    /**
     * The address that shows the file as an image: its preview for a HEIC,
     * which most browsers do not open, the file itself otherwise.
     */
    public function imageUrl(): string
    {
        return route($this->hasPreview() ? 'files.preview' : 'files.show', $this);
    }

    /**
     * Writes where the reading of the file stands, with the message to show (null clears it).
     *
     * @param  ReadingStatus  $status  the new state of the reading
     * @param  string|null  $error  why the file is not read, already translated
     */
    public function markReading(ReadingStatus $status, ?string $error = null): void
    {
        $this->forceFill(['reading_status' => $status, 'reading_error' => $error])->save();
    }

    /**
     * The oldest file of the family with the same content, waiting or of a document: this file is a copy of it.
     * Null when the file is no copy. Looked for once, or found with the others by `findCopies()`.
     */
    public function copyOf(): ?self
    {
        if ($this->copyOfFile === false) {
            $this->copyOfFile = $this->checksum === null ? null : self::originals()
                ->where('checksum', $this->checksum)
                ->where('id', '<', $this->id)
                ->first();
        }

        return $this->copyOfFile;
    }

    /**
     * Finds what each file is a copy of with one query, so that `copyOf()` asks nothing more.
     *
     * @param  Collection<int, self>  $files  files of the current family
     */
    public static function findCopies(Collection $files): void
    {
        $checksums = $files->pluck('checksum')->filter()->unique()->values();
        $originals = $checksums->isEmpty() ? collect() : self::originals()->whereIn('checksum', $checksums)->get();

        foreach ($files as $file) {
            $file->copyOfFile = $file->checksum === null ? null
                : $originals->first(static fn (self $original): bool => $original->checksum === $file->checksum && $original->id < $file->id);
        }
    }

    /**
     * Only the files that are no copy of an older one (see `copyOf()`), with one query.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function notCopies(Builder $query): void
    {
        $files = $this->getTable();

        $query->whereNotExists(static fn (QueryBuilder $older): QueryBuilder => $older->from($files, 'older')
            ->whereColumn('older.family_id', "{$files}.family_id")
            ->whereColumn('older.checksum', "{$files}.checksum")
            ->whereColumn('older.id', '<', "{$files}.id")
            ->where(static fn (QueryBuilder $kept): QueryBuilder => self::waitingOrFiled($kept, 'older')));
    }

    /**
     * The files of the family a copy can be of, the oldest first.
     *
     * @return Builder<self>
     */
    private static function originals(): Builder
    {
        return self::query()
            ->where(static fn (Builder $query): QueryBuilder => self::waitingOrFiled($query->getQuery(), $query->getModel()->getTable()))
            ->oldest('id');
    }

    /**
     * Keeps the files that count as originals of a copy: still waiting, or the file of a document.
     *
     * @param  QueryBuilder  $query  the query on the files
     * @param  string  $table  the name or alias of the files in the query
     */
    private static function waitingOrFiled(QueryBuilder $query, string $table): QueryBuilder
    {
        return $query->where("{$table}.to_sort", true)
            ->orWhereExists(static fn (QueryBuilder $documents): QueryBuilder => $documents->from('documents')->whereColumn('documents.file_id', "{$table}.id"));
    }

    /**
     * Only the files uploaded and still waiting for their document.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function toSort(Builder $query): void
    {
        $query->where('to_sort', true);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'to_sort' => 'boolean',
            'link_id' => 'integer',
            'preview_failed' => 'boolean',
            'reading_status' => ReadingStatus::class,
            'reading' => 'array',
            'source' => FileSource::class,
        ];
    }
}
