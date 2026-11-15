<?php

declare(strict_types=1);

namespace App\Actions\Diagnostics;

use App\Models\ServerError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps a reported exception as a short row in `server_errors`, with the request id, account, method and path of the
 * request that failed. It never throws: a failing write must not hide the original error from the log.
 */
final class RecordServerError
{
    /**
     * Writes the row for an exception the handler is reporting.
     */
    public function handle(Throwable $exception): void
    {
        try {
            // The request id is set by the middleware, so it tells a web request from a command or a job
            $request = Context::has('request_id') ? request() : null;
            $userId = Context::get('user_id');
            $familyId = Context::get('family_id');

            ServerError::create([
                'request_id' => Context::get('request_id'),
                'user_id' => is_int($userId) ? $userId : null,
                'family_id' => is_int($familyId) ? $familyId : null,
                'method' => $request?->method(),
                'path' => $request instanceof Request ? Str::limit($request->getRequestUri(), 2048, '') : null,
                'class' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 500, ''),
                'file' => Str::limit($exception->getFile(), 500, ''),
                'line' => $exception->getLine(),
            ]);
        } catch (Throwable) {
            // The original error is still reported to the log by the handler
        }
    }
}
