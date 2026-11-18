<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * The version the server runs (the `VERSION` file), asked by the open pages to find out that a new one was
 * released while they were open.
 */
final class VersionController
{
    /**
     * The version, never kept by the browser or a proxy: a kept answer would hide the release.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(
            ['version' => config()->string('app.version')],
            headers: ['Cache-Control' => 'no-store'],
        );
    }
}
