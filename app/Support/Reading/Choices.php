<?php

declare(strict_types=1);

namespace App\Support\Reading;

/**
 * The closed lists the model chooses from (`id: label`): an id it gives back is kept only if it is in the list,
 * so an invented id, or one of another family, never gets through.
 */
final class Choices
{
    /**
     * The id, if it is among the choices.
     *
     * @param  list<array{id: int, label: string}>  $choices  the list that was shown to the model
     * @param  int|null  $id  the id the model gave back
     * @return int|null the id, or null when it is null or not in the list
     */
    public static function pick(array $choices, ?int $id): ?int
    {
        if ($id === null) {
            return null;
        }

        return in_array($id, array_column($choices, 'id'), true) ? $id : null;
    }

    /**
     * The label of the id.
     *
     * @param  list<array{id: int, label: string}>  $choices  the list that was shown to the model
     * @param  int|null  $id  the id to look for
     * @return string|null the label, or null when the id is null or not in the list
     */
    public static function label(array $choices, ?int $id): ?string
    {
        foreach ($choices as $choice) {
            if ($choice['id'] === $id) {
                return $choice['label'];
            }
        }

        return null;
    }
}
