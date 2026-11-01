<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Models\User;

/**
 * What one account gets from the notices of a day: in the app, by email, or both.
 */
final readonly class Delivery
{
    /**
     * @param  User  $user  the account
     * @param  bool  $app  whether it gets the bell notice
     * @param  bool  $email  whether it gets the email
     */
    public function __construct(
        public User $user,
        public bool $app,
        public bool $email,
    ) {}
}
