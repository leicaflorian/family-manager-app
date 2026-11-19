<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Fills in `created_by` and `updated_by` with the signed-in account, like
 * `user_created` and `user_updated` in Directus. Outside a request (commands,
 * seeders) they stay as they are. Only an account of a family (guard `web`) is an
 * author, and never in the owner panel: there the default guard is `owner`, whose ids are
 * not accounts, and without a host of its own the panel shares the session of the app, where
 * a visit (*Enter as…*) in another tab signs in the account visited.
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 */
trait TracksAuthors
{
    /**
     * Hooks the filling into the creation and the update of the model.
     */
    public static function bootTracksAuthors(): void
    {
        static::creating(static function (Model $model): void {
            $author = self::author();

            if ($author !== null) {
                $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $author);
                $model->setAttribute('updated_by', $author);
            }
        });

        static::updating(static function (Model $model): void {
            $author = self::author();

            if ($author !== null) {
                $model->setAttribute('updated_by', $author);
            }
        });
    }

    /**
     * The id of the account signed in to the app, or null outside the app (the owner panel, commands).
     */
    private static function author(): ?int
    {
        if (Filament::isServing()) {
            return null;
        }

        $id = Auth::guard('web')->id();

        return is_int($id) ? $id : null;
    }
}
