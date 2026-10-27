<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use App\Services\FileStore;
use Illuminate\Http\UploadedFile;

/**
 * Puts the new photo on the person of the account and removes the previous one.
 */
final readonly class ChangePhoto
{
    public function __construct(private FileStore $files) {}

    /**
     * Saves the photo. An account without a person has nowhere to keep it.
     *
     * @param  User  $user  the account
     * @param  UploadedFile  $photo  the uploaded image
     */
    public function handle(User $user, UploadedFile $photo): void
    {
        $person = $user->person;

        if ($person === null) {
            return;
        }

        $previous = $person->photo()->first();
        $person->update(['photo_id' => $this->files->store($photo)->id]);

        if ($previous !== null) {
            $this->files->delete($previous);
        }
    }
}
