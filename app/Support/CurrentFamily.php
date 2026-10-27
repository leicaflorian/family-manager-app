<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The family whose data is being read and written.
 *
 * In a request it is the family of whoever signed in. Outside a request
 * (commands, seeders, tests) it is set by hand with `set()` and removed with
 * `clear()`. The state lives in Laravel's container as a *scoped* instance: it
 * starts empty on every request and every test, so a family set by hand does
 * not stick to the next one.
 */
final class CurrentFamily
{
    /** The family set by hand, which wins over the signed-in user's. */
    private ?int $override = null;

    /**
     * The id of the current family, or null if there is none.
     */
    public static function id(): ?int
    {
        $current = self::instance();

        if ($current->override !== null) {
            return $current->override;
        }

        $user = Auth::user();

        return $user instanceof User ? $user->family_id : null;
    }

    /**
     * Sets the family by hand, to work outside a request.
     *
     * @param  int  $id  the family id
     */
    public static function set(int $id): void
    {
        self::instance()->override = $id;
    }

    /**
     * Runs some work with the family set by hand, then puts back the previous one.
     *
     * For whoever writes a family's data without being signed in to it:
     * registration, the seeder, the copy from Directus. The previous family comes
     * back even if the work stops with an exception.
     *
     * @template T
     *
     * @param  int  $id  the family id
     * @param  callable(): T  $work  the work to run
     * @return T whatever the work returns
     */
    public static function using(int $id, callable $work): mixed
    {
        $current = self::instance();
        $previous = $current->override;
        $current->override = $id;

        try {
            return $work();
        } finally {
            $current->override = $previous;
        }
    }

    /**
     * Removes the family set by hand.
     */
    public static function clear(): void
    {
        self::instance()->override = null;
    }

    /**
     * The container instance that holds the family set by hand.
     */
    private static function instance(): self
    {
        return resolve(self::class);
    }
}
