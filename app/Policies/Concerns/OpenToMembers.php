<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\FamilyModel;
use App\Models\User;

/**
 * For the policies of models that a `member` also creates and edits (documents,
 * expenses, incomes). Deleting stays with an `admin`, as in `FamilyPolicy`,
 * which the policy extends.
 */
trait OpenToMembers
{
    /**
     * Creating: everyone.
     *
     * @param  User  $user  whoever signed in
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Editing: everyone of the family.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the row
     */
    public function update(User $user, FamilyModel $model): bool
    {
        return $this->ofFamily($user, $model);
    }
}
