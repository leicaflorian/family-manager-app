<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Person;
use App\Services\CategoryTree;
use App\Services\FileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a drawing straight to the gallery: the file is stored and the document is filed, with no reading by the AI
 * and without passing through *To sort*.
 */
final readonly class AddDrawing
{
    public function __construct(private FileStore $files, private CategoryTree $categories) {}

    /**
     * Stores the file and creates its document in the gallery category.
     *
     * @param  UploadedFile  $file  the picture or PDF
     * @param  int|null  $personId  the child
     * @param  string|null  $title  the title; empty becomes «Drawing by <name>», or «Drawing»
     * @param  string|null  $notes  the notes
     * @return Document the new document
     *
     * @throws ValidationException when the family has no gallery category
     */
    public function handle(UploadedFile $file, ?int $personId, ?string $title, ?string $notes): Document
    {
        $category = $this->categories->galleryCategory()
            ?? throw ValidationException::withMessages(['file' => __('Choose a gallery category in Categories first.')]);
        $person = $personId === null ? null : Person::query()->find($personId);
        $title = trim((string) $title);

        if ($title === '') {
            $title = $person === null ? __('Drawing') : __('Drawing by :name', ['name' => $person->first_name]);
        }

        return DB::transaction(fn (): Document => Document::query()->create([
            'title' => $title,
            'file_id' => $this->files->store($file)->id,
            'category_id' => $category->id,
            'status' => DocumentStatus::Active,
            'person_id' => $person?->id,
            'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
        ]));
    }
}
