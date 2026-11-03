<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\FamilyModel;
use App\Models\File;
use App\Models\User;

/**
 * The permissions on files: everyone sees them; who files documents has them read; a file waiting for its
 * document is deleted by an `admin`, or by a `member` when the member uploaded it or it is a copy. A file
 * that has its document goes only with the document.
 */
final class FilePolicy extends FamilyPolicy
{
    /**
     * Reading a file with the AI (*Read again*, *Fill from the file*): who may create its document, or change
     * the document it already has.
     *
     * @param  User  $user  whoever signed in
     * @param  File  $file  the file
     */
    public function read(User $user, File $file): bool
    {
        $document = $file->document;

        return $this->ofFamily($user, $file)
            && ($document === null ? $user->can('create', Document::class) : $user->can('update', $document));
    }

    /**
     * Deleting a file: never one that has its document; an `admin` any other, a `member` the files the member
     * uploaded and the copies.
     *
     * @param  User  $user  whoever signed in
     * @param  FamilyModel  $model  the file
     */
    public function delete(User $user, FamilyModel $model): bool
    {
        if (! $model instanceof File || ! $this->ofFamily($user, $model) || $model->document !== null) {
            return false;
        }

        return $user->isAdmin() || $model->created_by === $user->id || $model->copyOf() instanceof File;
    }
}
