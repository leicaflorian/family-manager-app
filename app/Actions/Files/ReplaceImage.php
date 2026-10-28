<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Models\File;
use App\Services\FileStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Puts a new image on a model of the family (the photo of a person, the logo
 * of an organization), or removes it, and deletes the previous file.
 */
final readonly class ReplaceImage
{
    public function __construct(private FileStore $files) {}

    /**
     * Saves the new image or removes the one there is. With neither, nothing changes.
     *
     * @param  Model  $owner  the model the image belongs to
     * @param  string  $column  the column with the id of the file, like `photo_id`
     * @param  UploadedFile|null  $image  the new image
     * @param  bool  $remove  true to remove the image there is, when no new one comes
     */
    public function handle(Model $owner, string $column, ?UploadedFile $image, bool $remove = false): void
    {
        if (! $image instanceof UploadedFile && ! $remove) {
            return;
        }

        $previousId = $owner->getAttribute($column);
        $previous = is_int($previousId) ? File::query()->find($previousId) : null;
        $owner->update([$column => $image instanceof UploadedFile ? $this->files->store($image)->id : null]);

        if ($previous !== null) {
            $this->files->delete($previous);
        }
    }
}
