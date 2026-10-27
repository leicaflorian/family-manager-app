<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The files of the family, read from the private disk. A file of another
 * family is not found (404), because it goes through the family scope.
 */
final class FileController extends Controller
{
    /**
     * Opens the file in the browser, with its type.
     */
    public function show(File $file): StreamedResponse
    {
        Gate::authorize('view', $file);

        return Storage::disk($file->disk)->response($file->path, $file->original_name, ['Content-Type' => $file->mime_type]);
    }

    /**
     * Downloads the file with the name it had.
     */
    public function download(File $file): StreamedResponse
    {
        Gate::authorize('view', $file);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}
