<?php

declare(strict_types=1);

namespace App\Actions\Diagnostics;

use App\Models\ServerError;
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
            // A job inherits the context (request id, account) but its `request()` is a console fake: only a request
            // that went through AssignRequestId carries the same id as an attribute. An error thrown before that
            // middleware runs has no request id, and so no method and path either.
            $requestId = Context::get('request_id');
            $request = request();
            $isWebRequest = is_string($requestId) && $request->attributes->get('request_id') === $requestId;
            $userId = Context::get('user_id');
            $familyId = Context::get('family_id');

            ServerError::create([
                'request_id' => $requestId,
                'user_id' => is_int($userId) ? $userId : null,
                'family_id' => is_int($familyId) ? $familyId : null,
                'method' => $isWebRequest ? $request->method() : null,
                'path' => $isWebRequest ? Str::limit('/'.ltrim($request->path(), '/'), 2048, '') : null,
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
