<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\FilePreviews;
use Carbon\CarbonImmutable;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
 * @property-read Document|null $document
 * @property-read Family $family
 *
 * @method static FileFactory factory($count = null, $state = [])
 * @method static Builder<static>|File newModelQuery()
 * @method static Builder<static>|File newQuery()
 * @method static Builder<static>|File query()
 * @method static Builder<static>|File toSort()
 *
 * @mixin \Eloquent
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size', 'to_sort', 'link_type', 'link_id'])]
final class File extends FamilyModel
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

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
        ];
    }
}
