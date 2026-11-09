<?php

declare(strict_types=1);

namespace App\Actions\Devices;

use App\Models\LinkedDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Forgets the phones linked to an account (all, or one): the phone and its session go, so it has to be linked again.
 */
final class ForgetLinkedDevices
{
    /**
     * Deletes the phones and their rows of `sessions`.
     *
     * @param  User  $user  the account
     * @param  LinkedDevice|null  $only  one phone of the account, or null for all
     * @return int how many phones were forgotten
     */
    public function handle(User $user, ?LinkedDevice $only = null): int
    {
        // Rows of any family: this also runs on a password reset, when nobody is signed in
        $query = LinkedDevice::query()->withoutGlobalScopes()->where('user_id', $user->id);

        if ($only instanceof LinkedDevice) {
            $query->whereKey($only->id);
        }

        $devices = $query->get(['id', 'session_id']);

        // Sessions first: a failure in between leaves a phone that is signed out, never a session without its phone
        DB::table('sessions')->whereIn('id', $devices->pluck('session_id')->filter()->all())->delete();

        return LinkedDevice::query()->withoutGlobalScopes()->whereKey($devices->modelKeys())->toBase()->delete();
    }
}
