<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FamilyModel;
use App\Models\User;

/**
 * The permissions on the notices of the bell: each is the business of the account it was sent to
 * (seeing it, marking it read), whatever the role; nobody creates or deletes one from the pages,
 * the morning round writes them and pruning removes them.
 */
final class ReminderNoticePolicy extends FamilyPolicy
{
    /**
     * Seeing the list: everyone, each sees only their own (the query filters on the account).
     *
     * @param  User  $user  whoever signed in
     */
    #[\Override]
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Seeing a notice: only the account it was sent to.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the notice
     */
    #[\Override]
    public function view(User $user, FamilyModel $model): bool
    {
        return $this->isOwn($user, $model);
    }

    /**
     * Creating: nobody, the morning round writes them.
     *
     * @param  User  $user  whoever signed in
     */
    #[\Override]
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Changing (marking it read): only the account it was sent to.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the notice
     */
    #[\Override]
    public function update(User $user, FamilyModel $model): bool
    {
        return $this->isOwn($user, $model);
    }

    /**
     * Deleting before choosing one: nobody.
     *
     * @param  User  $user  whoever signed in
     */
    #[\Override]
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Deleting: nobody.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the notice
     */
    #[\Override]
    public function delete(User $user, FamilyModel $model): bool
    {
        return false;
    }

    /**
     * Whether the notice was sent to this account (and so to this family).
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the notice
     */
    private function isOwn(User $user, FamilyModel $model): bool
    {
        return $this->ofFamily($user, $model) && $model->getAttribute('user_id') === $user->id;
    }
}
