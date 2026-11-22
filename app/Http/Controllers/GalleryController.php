<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\AddDrawing;
use App\Data\DrawingData;
use App\Data\DrawingYearData;
use App\Http\Requests\Documents\AddDrawingRequest;
use App\Http\Requests\Documents\GalleryRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Person;
use App\Models\User;
use App\Queries\FilterDocuments;
use App\Queries\GalleryDrawings;
use App\Services\CategoryTree;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The drawings gallery: the documents of the gallery category as pictures, by upload year, and adding a drawing.
 */
final class GalleryController extends Controller
{
    public function __construct(private readonly FilterDocuments $filter, private readonly CategoryTree $categories, private readonly GalleryDrawings $drawings) {}

    /**
     * *Drawings*: the pictures by year with the filters, the children that have drawings and the drawing opened.
     */
    public function index(GalleryRequest $request, #[CurrentUser] User $user): Response
    {
        $hasGallery = $this->categories->galleryCategory() instanceof DocumentCategory;

        $drawings = $this->drawings->query($request->person(), ['person'])
            ->when($request->filter() === 'favorites', fn ($query) => $query->where('documents.is_favorite', true))
            ->get();

        $years = $drawings
            ->groupBy(fn (Document $document): int => $document->file->uploadedAt()->year)
            ->map(fn (Collection $group, int $year): DrawingYearData => new DrawingYearData(
                $year,
                $group->count(),
                array_values($group->map(fn (Document $document): DrawingData => DrawingData::fromDocument($document, $user))->all()),
            ))
            ->sortKeysDesc()
            ->values()
            ->all();

        $selectedId = $request->drawing();
        $selected = $selectedId === null ? null : $this->drawings->query(null, ['person'])->where('documents.id', $selectedId)->first();

        return Inertia::render('documents/Gallery', [
            'years' => $years,
            'people' => $this->people(),
            'filters' => ['filter' => $request->filter(), 'person' => $request->person()],
            'selected' => $selected === null ? null : DrawingData::fromDocument($selected, $user),
            'total' => $drawings->count(),
            'canAdd' => $hasGallery && $user->can('create', Document::class),
            'canManageCategories' => $user->can('create', DocumentCategory::class),
            'hasGallery' => $hasGallery,
            'addOptions' => [
                'people' => $this->names(),
            ],
        ]);
    }

    /**
     * Adds a drawing to the gallery and stays on the page.
     *
     * @param  AddDrawingRequest  $request  the file with the child, the title and the notes
     * @param  AddDrawing  $add  the action
     * @return RedirectResponse back to the page
     */
    public function store(AddDrawingRequest $request, AddDrawing $add): RedirectResponse
    {
        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            $add->handle(
                $file,
                $request->filled('person_id') ? $request->integer('person_id') : null,
                $request->input('title') === null ? null : $request->string('title')->toString(),
                $request->input('notes') === null ? null : $request->string('notes')->toString(),
            );
        }

        return back();
    }

    /**
     * The children with at least one drawing in the gallery, by name.
     *
     * @return list<array{id: int, name: string}>
     */
    private function people(): array
    {
        $ids = $this->filter->apply(Document::query()->whereNotNull('person_id'), ['gallery' => true])
            ->reorder()->distinct()->pluck('person_id');

        return $this->names($ids->all());
    }

    /**
     * The people as `{id, name}`, by first name.
     *
     * @param  array<int, mixed>|null  $ids  only these people, all of the family when null
     * @return list<array{id: int, name: string}>
     */
    private function names(?array $ids = null): array
    {
        return array_values(Person::query()->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))->orderBy('first_name')->get(['id', 'first_name'])
            ->map(fn (Person $person): array => ['id' => $person->id, 'name' => $person->first_name])->all());
    }
}
