<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Context;

/**
 * Adds the account and its family to the log context once the request knows who is signed in,
 * so every log line of the request says whose it is. An owner of the panel belongs to no family and adds nothing.
 */
final class AddAccountToLogContext
{
    /**
     * @param  Authenticated  $event  the account found for the request
     */
    public function handle(Authenticated $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        Context::add(['user_id' => $event->user->id, 'family_id' => $event->user->family_id]);
    }
}
