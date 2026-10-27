<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Fills in `created_by` and `updated_by` with the signed-in account, like
 * `user_created` and `user_updated` in Directus. Outside a request (commands,
 * seeders) they stay as they are.
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
            $author = Auth::id();

            if ($author !== null) {
                $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $author);
                $model->setAttribute('updated_by', $author);
            }
        });

        static::updating(static function (Model $model): void {
            $author = Auth::id();

            if ($author !== null) {
                $model->setAttribute('updated_by', $author);
            }
        });
    }
}
