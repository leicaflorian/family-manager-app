<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pins a personal document at the top of its person, or unpins it. Pinning twice keeps the first date.
 */
final readonly class PinDocument
{
    /**
     * Pins or unpins the document.
     *
     * @param  Document  $document  the document
     * @param  User  $user  who pins it
     * @param  bool  $pinned  true to pin, false to unpin
     *
     * @throws ValidationException when pinning a document that is not personal
     */
    public function handle(Document $document, User $user, bool $pinned): void
    {
        Gate::authorize('update', $document);

        if (! $pinned) {
            $document->forceFill(['pinned_at' => null, 'pinned_by' => null])->save();

            return;
        }

        if (! $document->isPersonal()) {
            throw ValidationException::withMessages(['document' => __('Only a personal document can be pinned.')]);
        }

        if ($document->pinned_at === null) {
            $document->forceFill(['pinned_at' => now(), 'pinned_by' => $user->id])->save();
        }
    }
}
