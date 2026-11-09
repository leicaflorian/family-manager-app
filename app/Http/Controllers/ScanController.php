<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reading\QueueReading;
use App\Enums\FileSource;
use App\Http\Requests\Scan\ScanRequest;
use App\Models\File;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\FileStore;
use App\Support\ScanFileName;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Scan*: photographing the pages of a document with the phone. For now only the page is there.
 */
final class ScanController extends Controller
{
    /**
     * The page of the scan.
     */
    public function show(): Response
    {
        return Inertia::render('scan/Scan');
    }

    /**
     * Saves the files sent by the phone (or the scanner) to *To sort*, each with the name of the moment and the note,
     * queues their reading as *Upload* does, and marks the last upload of the linked phone. Answers the saved files.
     */
    public function store(ScanRequest $request, FileStore $store, QueueReading $queue, #[CurrentUser] User $user): JsonResponse
    {
        $source = $request->source();
        $now = CarbonImmutable::now();
        $note = $request->filled('note') ? $request->string('note')->toString() : null;
        // the scanner names the files itself; the phone never chooses
        $written = $source === FileSource::Scanner ? $request->string('name')->toString() : '';
        /** @var Collection<int, string> $taken */
        $taken = File::query()->pluck('original_name')->values();
        $saved = [];

        foreach ($request->uploadedFiles() as $file) {
            $name = ScanFileName::for($source, $now, $file->getClientOriginalExtension() ?: ($file->guessExtension() ?? ''), $taken, $written);
            $taken->push($name);

            $stored = $store->storePath($file->getPathname(), $name, $file->getMimeType() ?? 'application/octet-stream', true, $source, $note);
            $queue->handle($stored, $user, app()->getLocale());
            $saved[] = ['id' => $stored->id, 'name' => $stored->original_name, 'size' => $stored->size];
        }

        $deviceId = $request->session()->get('linked_device_id');

        if (is_int($deviceId)) {
            LinkedDevice::query()->whereKey($deviceId)->where('user_id', $user->id)->update(['last_upload_at' => $now]);
        }

        return response()->json(['files' => $saved]);
    }
}
