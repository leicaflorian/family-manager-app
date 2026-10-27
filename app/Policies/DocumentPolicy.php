<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FamilyModel;
use App\Models\User;

/**
 * The permissions on documents: a `member` uploads and edits them too.
 * Deleting them stays with an `admin`.
 */
final class DocumentPolicy extends FamilyPolicy
{
    /**
     * Uploading a document: everyone.
     *
     * @param  User  $user  whoever signed in
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Editing a document: everyone of its family.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the document
     */
    public function update(User $user, FamilyModel $model): bool
    {
        return $this->ofFamily($user, $model);
    }
}
