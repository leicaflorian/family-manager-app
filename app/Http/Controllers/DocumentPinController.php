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
     */
    public function store(#[CurrentUser] User $user, Document $document, PinDocument $pin): RedirectResponse
    {
        $pin->handle($document, $user, true);

        return back();
    }

    /**
     * Unpins the document.
     */
    public function destroy(#[CurrentUser] User $user, Document $document, PinDocument $pin): RedirectResponse
    {
        $pin->handle($document, $user, false);

        return back();
    }
}
