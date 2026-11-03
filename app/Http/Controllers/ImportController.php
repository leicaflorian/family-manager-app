<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reading\ImportFiles;
use App\Data\PendingFileData;
use App\Http\Requests\Reading\ImportRequest;
use App\Models\Document;
use App\Models\File;
use App\Services\DocumentFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Import more files*: several files of *To sort*, read, checked one by one on one page and filed as
 * documents together.
 */
final class ImportController extends Controller
{
    /**
     * The page with the files of `?files=1,2,3`, in that order. A file that is not waiting in the family
     * is a 404 before anything else; with no files the person goes back to *To sort*.
     */
    public function index(Request $request, DocumentFormOptions $options): Response|RedirectResponse
    {
        $ids = $this->ids($request->query('files'));

        if ($ids === []) {
            return to_route('to-sort');
        }

        $files = File::query()->toSort()->with('document')->whereKey($ids)->get();
        abort_unless($files->count() === count($ids), 404);
        Gate::authorize('create', Document::class);

        $ordered = $files->sortBy(static fn (File $file): int|false => array_search($file->id, $ids, true))->values();
        $failures = $request->session()->get('import_failures', []);

        return Inertia::render('documents/Import', [
            'files' => PendingFileData::fromFiles($ordered),
            'failures' => is_array($failures) ? $failures : [],
            ...$options->all(),
        ]);
    }

    /**
     * Files the documents. What fails stays on the page, each file with its reason; with nothing left to
     * check the person goes to *Documents*.
     */
    public function store(ImportRequest $request, ImportFiles $import): RedirectResponse
    {
        $failures = $import->handle($request->entries());
        $waiting = File::query()->toSort()->whereKey(array_keys($failures))->pluck('id');

        return $waiting->isEmpty()
            ? to_route('documents.index')
            : to_route('import', ['files' => $waiting->implode(',')])->with('import_failures', $failures);
    }

    /**
     * The ids of the query, without duplicates.
     *
     * @param  mixed  $value  `?files=` as it came
     * @return list<int>
     */
    private function ids(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return array_values(array_unique(array_map(intval(...), array_filter(explode(',', $value), is_numeric(...)))));
    }
}
