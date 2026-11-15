<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a new id, puts it in the log context and sends it back in `X-Request-Id`.
 * An id sent by the client is ignored: the id is ours, so a report can be matched with the log line.
 */
final class AssignRequestId
{
    /** The response header that carries the id. */
    public const string HEADER = 'X-Request-Id';

    /**
     * @param  Request  $request  the request
     * @param  Closure(Request): Response  $next  the rest of the stack
     * @return Response the response with the id header
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        Context::add('request_id', $id);
        // A queued job inherits the context but not this attribute: it tells a real request from a job
        $request->attributes->set('request_id', $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
