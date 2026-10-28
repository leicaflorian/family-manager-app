<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FamilyModel;
use App\Models\User;

/**
 * The permissions on the data of a family, the same for every model: everyone
 * sees everything, only an `admin` creates, edits and deletes.
 *
 * That the row belongs to the family is already guaranteed by the scope (a row
 * of another family is not found, and answers 404); the check here is a second
 * line of defence, for rows read without the scope.
 */
class FamilyPolicy
{
    /**
     * Seeing the list: everyone.
     *
     * @param  User  $user  whoever signed in
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Seeing a row: everyone of its family.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the row
     */
    public function view(User $user, FamilyModel $model): bool
    {
        return $this->ofFamily($user, $model);
    }

    /**
     * Creating: only an `admin`.
     *
     * @param  User  $user  whoever signed in
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Editing: only an `admin` of the family.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the row
     */
    public function update(User $user, FamilyModel $model): bool
    {
        return $user->isAdmin() && $this->ofFamily($user, $model);
    }

    /**
     * Deleting rows of the family, before choosing one: only an `admin`.
     *
     * @param  User  $user  whoever signed in
     */
    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Deleting: only an `admin` of the family.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the row
     */
    public function delete(User $user, FamilyModel $model): bool
    {
        return $this->deleteAny($user) && $this->ofFamily($user, $model);
    }

    /**
     * Whether the row belongs to the family of whoever signed in.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the row
     */
    protected function ofFamily(User $user, FamilyModel $model): bool
    {
        return $model->family_id === $user->family_id;
    }
}
