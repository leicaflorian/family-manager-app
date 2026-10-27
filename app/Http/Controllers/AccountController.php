<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Account\ChangePhoto;
use App\Actions\Account\UpdateAccount;
use App\Data\AccountData;
use App\Http\Requests\Settings\ChangePasswordRequest;
use App\Http\Requests\Settings\DeleteAccountRequest;
use App\Http\Requests\Settings\UpdateAccountRequest;
use App\Http\Requests\Settings\UploadPhotoRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *My account*: the data, the password, the photo, the deletion. It concerns only
 * whoever signed in, not the family.
 */
final class AccountController extends Controller
{
    /**
     * The page.
     */
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/Account', ['account' => AccountData::fromUser($user)]);
    }

    /**
     * Saves first name, last name and email.
     */
    public function update(UpdateAccountRequest $request, #[CurrentUser] User $user, UpdateAccount $updateAccount): RedirectResponse
    {
        $updateAccount->handle(
            $user,
            $request->string('first_name')->toString(),
            $request->string('last_name')->toString(),
            $request->string('email')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return to_route('account.edit');
    }

    /**
     * Changes the password.
     */
    public function password(ChangePasswordRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->update(['password' => $request->string('password')->toString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password changed.')]);

        return to_route('account.edit');
    }

    /**
     * Puts the new photo.
     */
    public function photo(UploadPhotoRequest $request, #[CurrentUser] User $user, ChangePhoto $changePhoto): RedirectResponse
    {
        $photo = $request->file('photo');

        if ($photo instanceof UploadedFile) {
            $changePhoto->handle($user, $photo);
        }

        return to_route('account.edit');
    }

    /**
     * Deletes the account and goes to *Sign in*. The person stays in the family,
     * without an account. The last `admin` cannot be deleted.
     *
     * @throws ValidationException if it is the last admin of the family
     */
    public function destroy(DeleteAccountRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        if ($user->isLastAdmin()) {
            throw ValidationException::withMessages(['password' => __('You are the only administrator of the family: first give the level "Everything, like you" to another person.')]);
        }

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
