<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\DocumentReader;
use App\Exceptions\Reading\ReadingFailed;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * *Scan*, the sheet in a photo: the four corners of the paper that the AI finds, so that the page can crop the photo.
 * It is a paid call, so each account has a number a day (the day of Rome); the photo is never saved.
 */
final class ScanCornersController extends Controller
{
    /** The biggest photo taken, in bytes. */
    private const int MAX_BYTES = 1024 * 1024;

    /** The searches an account may make in a day. */
    private const int MAX_A_DAY = 50;

    /**
     * Finds the corners of the sheet in the JPEG of the body. Answers `{corners: …}` with the four points from 0 to 1,
     * clockwise from the top left one, or `null` when there is no sheet; 413 for a photo over 1 MB, 400 when it is not
     * a JPEG, 503 with the reading off and 429 after the limit of the day.
     *
     * @param  Request  $request  the photo as the raw body
     * @param  DocumentReader  $reader  the AI reader
     */
    public function __invoke(Request $request, DocumentReader $reader): JsonResponse
    {
        if (! $reader->isActive()) {
            return response()->json(['message' => __('The edges are set by hand')], 503);
        }

        $photo = $request->getContent();

        if ((int) $request->header('Content-Length', '0') > self::MAX_BYTES || strlen($photo) > self::MAX_BYTES) {
            return response()->json(['message' => __('The photo is too big (1 MB at most)')], 413);
        }

        if (new \finfo(FILEINFO_MIME_TYPE)->buffer($photo) !== 'image/jpeg') {
            return response()->json(['message' => __('Only a JPEG photo is accepted')], 400);
        }

        $now = CarbonImmutable::now('Europe/Rome');
        $key = sprintf('corners:%d:%s', Auth::id(), $now->format('Y-m-d'));

        if (RateLimiter::tooManyAttempts($key, self::MAX_A_DAY)) {
            return response()->json(['message' => __('The edges are set by hand for today')], 429);
        }

        // the search is counted when it starts: a failed one was paid all the same
        RateLimiter::hit($key, max(1, (int) $now->diffInSeconds($now->endOfDay(), true) + 1));

        try {
            $corners = $reader->corners($photo);
        } catch (ReadingFailed $failure) {
            report($failure);
            $corners = null;
        }

        return response()->json(['corners' => $corners]);
    }
}
