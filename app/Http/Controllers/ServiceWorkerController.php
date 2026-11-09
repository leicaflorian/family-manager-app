<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The service worker of the app. Vite builds it as `public/build/sw.js` (no hash in the name, the browser asks for
 * it by the same address every time), and it must be served from the root: a worker controls only the paths below
 * its own, so `/build/sw.js` could not take *Share* on `/scan/share`.
 */
final class ServiceWorkerController
{
    /**
     * The script, to be checked by the browser at every visit (`no-cache`) so a new version is found at once.
     *
     * @throws NotFoundHttpException when the frontend was not built
     */
    public function __invoke(): BinaryFileResponse
    {
        $script = public_path('build/sw.js');

        abort_unless(is_file($script), 404);

        return response()->file($script, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, max-age=0',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
