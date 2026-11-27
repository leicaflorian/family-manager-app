<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BankFilterAction;
use App\Models\BankFilter;
use App\Models\FamilyModel;
use App\Models\User;

/**
 * The permissions on the filters of the bank: everyone sees them; an `admin` does everything; a `member` creates,
 * changes and deletes the ones that do not pay a due date (paying settles a planned item, which only an `admin` edits).
 */
final class BankFilterPolicy extends FamilyPolicy
{
    /**
     * Creating: an `admin`, or a `member` when the action is not paying a due date.
     *
     * @param  User  $user  whoever signed in
     * @param  BankFilterAction|null  $action  what the new filter does, as the second argument of the check; without it only an `admin`
     */
    public function create(User $user, ?BankFilterAction $action = null): bool
    {
        return $user->isAdmin() || ($action instanceof BankFilterAction && $action !== BankFilterAction::Pay);
    }

    /**
     * Editing: an `admin` of the family, or a member of it when the filter does not pay a due date.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the filter
     */
    public function update(User $user, FamilyModel $model): bool
    {
        return $this->ofFamily($user, $model) && ($user->isAdmin() || ! $this->pays($model));
    }

    /**
     * Deleting: like editing.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the filter
     */
    public function delete(User $user, FamilyModel $model): bool
    {
        return $this->update($user, $model);
    }

    /**
     * Whether the filter pays a due date.
     *
     * @param  FamilyModel  $model  the filter
     */
    private function pays(FamilyModel $model): bool
    {
        return $model instanceof BankFilter && $model->action === BankFilterAction::Pay;
    }
}
