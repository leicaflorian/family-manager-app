<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * The fallback of *Share* on Android. The service worker takes the request of the system menu before it gets here
 * and keeps the files on the phone; this runs only when the worker is not active yet, and sends the browser to the
 * *Scan* page with nothing in it. It reads nothing from the request and stores nothing: files shared by a phone
 * stay on the phone until the person uploads them from *Scan*.
 */
final class ScanShareController
{
    /**
     * Sends the browser to the *Scan* page, with a 303 so the POST becomes a GET.
     */
    public function __invoke(): RedirectResponse
    {
        return to_route('scan', status: 303);
    }
}
