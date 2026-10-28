<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\FilePreviews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The files of the family, read from the private disk. A file of another
 * family is not found (404), because it goes through the family scope; so is
 * a file whose content is missing, as one copied from Directus can be.
 */
final class FileController extends Controller
{
    /**
     * Opens the file in the browser, with its type, kept in the browser cache.
     * Answers 304 when the browser already has this version.
     */
    public function show(Request $request, File $file): Response
    {
        Gate::authorize('view', $file);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        $response = Storage::disk($file->disk)->response($file->path, $file->original_name, ['Content-Type' => $file->mime_type]);

        return $this->cached($request, $response, 'file-'.$file->id.'-'.$file->size);
    }

    /**
     * Serves the JPEG preview of a PDF or HEIC file, made on the first request
     * and kept in the browser cache. A file with no preview is not found (404).
     */
    public function preview(Request $request, File $file, FilePreviews $previews): Response
    {
        Gate::authorize('view', $file);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        $path = $previews->path($file);
        abort_if($path === null, 404);

        $response = Storage::disk($file->disk)->response($path, null, ['Content-Type' => 'image/jpeg']);

        return $this->cached($request, $response, 'preview-'.$file->id.'-'.basename($path));
    }

    /**
     * Downloads the file with the name it had.
     */
    public function download(File $file): StreamedResponse
    {
        Gate::authorize('view', $file);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    /**
     * Lets the browser keep the response, for this user only (private), and
     * turns it into a 304 with no body when the browser already has this
     * version (same ETag). It is kept for one week, 604800 seconds: on a
     * shared computer the file stays in the browser cache after signing out,
     * and a week is the compromise between that and not downloading the same
     * page again every time.
     *
     * @param  Request  $request  the request, with the ETag the browser has
     * @param  Response  $response  the response to cache
     * @param  string  $etag  identifies this version of the content
     */
    private function cached(Request $request, Response $response, string $etag): Response
    {
        $response->setPrivate();
        $response->setMaxAge(604800);
        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }
}
