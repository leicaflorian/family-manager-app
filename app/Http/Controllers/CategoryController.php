<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cards\CardTypes;
use App\Http\Requests\Settings\SaveCategoryRequest;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Services\CategoryTree;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Document categories*: the tree, and for an `admin` adding, renaming, moving
 * and deleting.
 */
final class CategoryController extends Controller
{
    /**
     * The tree with the number of documents of each category.
     */
    public function index(CategoryTree $tree, #[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/Categories', [
            'categories' => $tree->tree(),
            'cardTypes' => CardTypes::options(),
            'canEdit' => $user->can('create', DocumentCategory::class),
        ]);
    }

    /**
     * A new category, at the end of those in the same place.
     */
    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        $parent = $request->integer('parent_id') ?: null;
        $last = DocumentCategory::query()->where('parent_id', $parent)->orderByDesc('sort')->first();

        DB::transaction(function () use ($request, $parent, $last): void {
            // A new gallery takes the place of the one the family had
            if ($request->boolean('gallery')) {
                DocumentCategory::query()->where('gallery', true)->update(['gallery' => false]);
            }

            DocumentCategory::query()->create([
                'name' => $request->string('name')->toString(),
                'parent_id' => $parent,
                'sort' => ($last->sort ?? 0) + 1,
                'card_type' => $request->validated('card_type'),
                'gallery' => $request->boolean('gallery'),
            ]);
        });

        return to_route('categories.index');
    }

    /**
     * The new name, the new place in the tree, the card and whether it is the gallery.
     */
    public function update(SaveCategoryRequest $request, DocumentCategory $category): RedirectResponse
    {
        DB::transaction(function () use ($request, $category): void {
            // A family has a single gallery: marking this one switches the other off
            if ($request->boolean('gallery')) {
                DocumentCategory::query()->where('gallery', true)->whereKeyNot($category->id)->update(['gallery' => false]);
            }

            $category->update([
                'name' => $request->string('name')->toString(),
                'parent_id' => $request->integer('parent_id') ?: null,
                // A request without the field leaves the card and the gallery as they are
                ...$request->has('card_type') ? ['card_type' => $request->validated('card_type')] : [],
                ...$request->has('gallery') ? ['gallery' => $request->boolean('gallery')] : [],
            ]);
        });

        return to_route('categories.index');
    }

    /**
     * Deletes an empty category: without documents and without subcategories.
     *
     * @throws ValidationException if the category has documents or subcategories
     */
    public function destroy(DocumentCategory $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->loadCount(['documents', 'children']);

        if ($category->documents_count > 0) {
            throw ValidationException::withMessages(['category' => trans_choice(
                'There is :count document in this category: move it to another one first.|There are :count documents in this category: move them to another one first.',
                (int) $category->documents_count,
            )]);
        }

        if ($category->children_count > 0) {
            throw ValidationException::withMessages(['category' => __('This category has subcategories: move or delete them first.')]);
        }

        $category->delete();

        return to_route('categories.index');
    }
}
