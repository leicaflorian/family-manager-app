<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * I file della famiglia, letti dal disco privato. Un file di un'altra famiglia
 * non si trova (404), perché passa dallo scope della famiglia.
 */
final class FileController extends Controller
{
    /**
     * Apre il file nel browser, con il suo tipo.
     */
    public function show(File $file): StreamedResponse
    {
        Gate::authorize('view', $file);

        return Storage::disk($file->disk)->response($file->path, $file->original_name, ['Content-Type' => $file->mime_type]);
    }

    /**
     * Scarica il file con il nome che aveva.
     */
    public function scarica(File $file): StreamedResponse
    {
        Gate::authorize('view', $file);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}
