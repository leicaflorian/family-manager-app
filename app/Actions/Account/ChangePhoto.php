<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Files\ReplaceImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Puts the new photo on the person of the account and removes the previous one.
 */
final readonly class ChangePhoto
{
    public function __construct(private ReplaceImage $replace) {}

    /**
     * Saves the photo. An account without a person has nowhere to keep it.
     *
     * @param  User  $user  the account
     * @param  UploadedFile  $photo  the uploaded image
     */
    public function handle(User $user, UploadedFile $photo): void
    {
        if ($user->person !== null) {
            $this->replace->handle($user->person, 'photo_id', $photo);
        }
    }
}
