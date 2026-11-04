<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\RedirectResponse;

/**
 * The way to *Import more files* for files just sent: by *Upload*, by the form of a card, or back after a
 * partial failure. The card they come from travels in `?link=`, so the page goes back to it.
 */
final class ImportPage
{
    /**
     * Opens *Import* with these files, in this order, keeping the card they come from.
     *
     * @param  iterable<int>  $ids  the ids of the files
     * @param  mixed  $link  the card as `<type>:<id>`, as the request sends it; one of another family is dropped
     */
    public static function open(iterable $ids, mixed $link = null): RedirectResponse
    {
        $files = implode(',', is_array($ids) ? $ids : iterator_to_array($ids, false));

        return to_route('import', ['files' => $files, ...PresetLink::query($link)]);
    }
}
