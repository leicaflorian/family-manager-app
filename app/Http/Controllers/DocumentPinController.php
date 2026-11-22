<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\PinDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Pins a personal document at the top of its person and unpins it.
 */
final class DocumentPinController extends Controller
{
    /**
     * Pins the document.
     *
     * @param  User  $user  who pins it
     * @param  Document  $document  the personal document
     * @param  PinDocument  $pin  the action
     * @return RedirectResponse back to the page
     */
    public function store(#[CurrentUser] User $user, Document $document, PinDocument $pin): RedirectResponse
    {
        $pin->handle($document, $user, true);

        return back();
    }

    /**
     * Unpins the document.
     *
     * @param  User  $user  who unpins it
     * @param  Document  $document  the pinned document
     * @param  PinDocument  $pin  the action
     * @return RedirectResponse back to the page
     */
    public function destroy(#[CurrentUser] User $user, Document $document, PinDocument $pin): RedirectResponse
    {
        $pin->handle($document, $user, false);

        return back();
    }
}
