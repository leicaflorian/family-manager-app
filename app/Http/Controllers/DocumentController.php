<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\ChangeFile;
use App\Data\DocumentData;
use App\Data\DocumentDetailData;
use App\Http\Requests\Documents\ChangeFileRequest;
use App\Http\Requests\Documents\DocumentIndexRequest;
use App\Http\Requests\Documents\SaveDocumentRequest;
use App\Models\Document;
use App\Models\File;
use App\Models\User;
use App\Queries\FilterDocuments;
use App\Services\CategoryTree;
use App\Services\DocumentFormOptions;
use App\Services\DocumentLinks;
use App\Services\FileStore;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The documents of the family: the list with its filters, a document, its
 * change, the change of its file and its deletion, and filing a file of
 * *Upload*.
 */
final class DocumentController extends Controller
{
    /** How many documents a page of the list holds. */
    private const int PER_PAGE = 15;

    public function __construct(private readonly CategoryTree $categories, private readonly DocumentLinks $links) {}

    /**
     * *Documents*: the filtered list, 30 at a time, the choices of the filters
     * and, on the computer, the document chosen on the right.
     */
    public function index(DocumentIndexRequest $request, FilterDocuments $filter, DocumentFormOptions $options, #[CurrentUser] User $user): Response
    {
        $filters = $request->filters();
        $page = $filter->apply(Document::query()->with(['person.photo', 'organization', 'file']), $filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();
        $paths = $this->categories->paths();
        $selected = $request->filled('document') ? Document::query()->find($request->integer('document')) : null;

        return Inertia::render('documents/Documents', [
            'documents' => array_map(static fn (Document $document): DocumentData => DocumentData::fromDocument($document, $paths), $page->items()),
            'total' => $page->total(),
            'nextPage' => $page->nextPageUrl(),
            'previousPage' => $page->previousPageUrl(),
            'filters' => $filters,
            'hasDocuments' => $page->total() > 0 || Document::query()->exists(),
            'options' => $options->filters(),
            'selected' => $selected === null ? null : $this->detail($selected, $user),
        ]);
    }

    /**
     * *A document*.
     */
    public function show(Document $document, #[CurrentUser] User $user): Response
    {
        return Inertia::render('documents/Document', ['document' => $this->detail($document, $user)]);
    }

    /**
     * *Edit a document*: its form with every field and the links.
     */
    public function edit(Document $document, DocumentFormOptions $options, #[CurrentUser] User $user): Response
    {
        Gate::authorize('update', $document);

        return Inertia::render('documents/EditDocument', [
            'document' => $this->detail($document, $user),
            'options' => $options->all(),
        ]);
    }

    /**
     * Files a file of *Upload*: the document, with its links. Back to
     * *Upload* while other files wait, otherwise to *Documents*.
     */
    public function store(SaveDocumentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $document = Document::query()->create([...$request->fields(), 'file_id' => $request->integer('file_id')]);
            $document->file->update(['to_sort' => false]);
            $this->links->sync($document, $request->links());
        });

        return File::query()->toSort()->exists() ? to_route('upload') : to_route('documents.index');
    }

    /**
     * Saves the change: the fields and the links.
     */
    public function update(SaveDocumentRequest $request, Document $document): RedirectResponse
    {
        DB::transaction(function () use ($request, $document): void {
            $document->update($request->fields());
            $this->links->sync($document, $request->links());
        });

        return to_route('documents.show', $document);
    }

    /**
     * Puts the new file chosen in *Edit* and stays there.
     */
    public function file(ChangeFileRequest $request, Document $document, ChangeFile $changeFile): RedirectResponse
    {
        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            $changeFile->handle($document, $file);
        }

        return to_route('documents.edit', $document);
    }

    /**
     * Deletes the document, with its links and its file (only an `admin`).
     */
    public function destroy(Document $document, FileStore $store): RedirectResponse
    {
        Gate::authorize('delete', $document);

        DB::transaction(function () use ($document, $store): void {
            $this->links->sync($document, []);
            $document->delete();
            $store->delete($document->file);
        });

        return to_route('documents.index');
    }

    /**
     * The data of a document for its pages.
     */
    private function detail(Document $document, User $user): DocumentDetailData
    {
        $document->loadMissing(['person.photo', 'organization', 'file']);

        return DocumentDetailData::fromDocument(
            $document,
            $this->categories->paths()[$document->category_id ?? 0] ?? null,
            $this->links->of($document),
            $user,
        );
    }
}
