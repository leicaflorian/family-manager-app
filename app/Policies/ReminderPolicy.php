<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FamilyModel;
use App\Models\Reminder;
use App\Models\User;
use App\Policies\Concerns\OpenToMembers;

/**
 * The permissions on reminders: a `member` creates, edits, marks done and reopens them too, only an `admin` deletes.
 * An automatic reminder is made from a date of a card or a document: nobody edits or deletes it, and it can still be
 * marked done and reopened.
 */
final class ReminderPolicy extends FamilyPolicy
{
    use OpenToMembers {
        update as private updateOfFamily;
    }

    /**
     * Editing: whoever is of the family, unless the reminder is automatic.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the reminder
     */
    public function update(User $user, FamilyModel $model): bool
    {
        return $this->updateOfFamily($user, $model) && ! $this->isAutomatic($model);
    }

    /**
     * Deleting: only an `admin` of the family, and never an automatic reminder.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the reminder
     */
    public function delete(User $user, FamilyModel $model): bool
    {
        return parent::delete($user, $model) && ! $this->isAutomatic($model);
    }

    /**
     * Marking done and reopening: whoever can see it.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the reminder
     */
    public function markDone(User $user, FamilyModel $model): bool
    {
        return $this->view($user, $model);
    }

    /**
     * Whether the reminder is made by the app from a date.
     *
     * @param  FamilyModel  $model  the reminder
     */
    private function isAutomatic(FamilyModel $model): bool
    {
        return $model instanceof Reminder && $model->is_automatic;
    }
}
