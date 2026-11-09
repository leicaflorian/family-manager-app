<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FamilyModel;
use App\Models\User;

/**
 * The permissions on the linked phones: everyone sees the list (each page filters on its own account),
 * and only the account a phone is linked to forgets it, whatever the role.
 */
final class LinkedDevicePolicy extends FamilyPolicy
{
    /**
     * Linking happens from the link of the phone, not from the pages: nobody.
     *
     * @param  User  $user  whoever signed in
     */
    #[\Override]
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Changing: nobody.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the phone
     */
    #[\Override]
    public function update(User $user, FamilyModel $model): bool
    {
        return false;
    }

    /**
     * Forgetting before choosing one: everyone (each can forget only their own).
     *
     * @param  User  $user  whoever signed in
     */
    #[\Override]
    public function deleteAny(User $user): bool
    {
        return true;
    }

    /**
     * Forgetting: only the account the phone is linked to.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the phone
     */
    #[\Override]
    public function delete(User $user, FamilyModel $model): bool
    {
        return $this->ofFamily($user, $model) && $model->getAttribute('user_id') === $user->id;
    }
}
