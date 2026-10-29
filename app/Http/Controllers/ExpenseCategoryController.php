<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Data\OptionData;
use App\Http\Requests\Settings\SaveExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\ExpenseCategoryTree;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Expense categories*: the tree, and for an `admin` the card type of a main category.
 */
final class ExpenseCategoryController extends Controller
{
    /**
     * The tree with the card type of each category, and the card types to choose from.
     */
    public function index(ExpenseCategoryTree $tree, #[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/ExpenseCategories', [
            'categories' => $tree->tree(),
            'cardTypes' => array_map(static fn (CardType $type): OptionData => new OptionData($type->type(), $type->label()), CardTypes::all()),
            'canEdit' => $user->can('create', ExpenseCategory::class),
        ]);
    }

    /**
     * The new card type of a main category, or none.
     */
    public function update(SaveExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $category->update(['card_type' => $request->validated('card_type')]);

        return to_route('expense-categories.index');
    }
}
