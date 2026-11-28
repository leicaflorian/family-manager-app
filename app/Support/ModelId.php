<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The number that identifies a record, typed: Eloquent gives the key as `mixed`.
 */
final class ModelId
{
    /**
     * The id of the record.
     *
     * @param  Model  $model  the record
     *
     * @throws InvalidArgumentException if the record has no numeric id
     */
    public static function of(Model $model): int
    {
        $id = $model->getKey();

        return is_int($id) ? $id : throw new InvalidArgumentException($model::class.' has no numeric id.');
    }

    /**
     * An id sent by a form or a JSON body, or null: any positive number counts, a JSON float such as `12.0` too, so
     * that the id looked for in the family is the same one that gets saved.
     *
     * @param  mixed  $value  what arrived
     */
    public static function sent(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
