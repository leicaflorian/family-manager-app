<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LinkedDevice;
use Illuminate\Auth\Events\Login;

/**
 * A login with the password on a linked phone opens the whole app: the mark of the phone leaves the session.
 * The sign-in by cookie writes the mark after `Auth::login`, so it is not undone here.
 */
final class LiftLinkedDeviceLimit
{
    /**
     * @param  Login  $event  the login
     */
    public function handle(Login $event): void
    {
        if ($event->guard === 'web' && session()->has(LinkedDevice::SESSION_KEY)) {
            session()->forget(LinkedDevice::SESSION_KEY);
        }
    }
}
