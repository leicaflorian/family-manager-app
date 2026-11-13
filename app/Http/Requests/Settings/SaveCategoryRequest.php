<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Cards\CardTypes;
use App\Models\DocumentCategory;
use App\Rules\OfFamily;
use App\Services\CategoryTree;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A document category, new or changed: the name, where it sits and the card its documents go with.
 */
final class SaveCategoryRequest extends FormRequest
{
    /**
     * Only an `admin` changes the categories.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof DocumentCategory
            ? $this->user()?->can('update', $category) ?? false
            : $this->user()?->can('create', DocumentCategory::class) ?? false;
    }

    /**
     * The rules of the fields. The card is empty or one of the card types, on a main category and on a subcategory alike. A category does not go under itself nor under one of its children.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', new OfFamily('document_categories'), $this->notUnderItself(...)],
            'card_type' => ['nullable', 'string', Rule::in(CardTypes::types())],
        ];
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => __('name'), 'parent_id' => __('inside'), 'card_type' => __('Card')];
    }

    /**
     * Rejects as parent the category itself or one that sits below it.
     *
     * @param  Closure(string): mixed  $fail
     */
    private function notUnderItself(string $attribute, mixed $value, Closure $fail): void
    {
        $category = $this->route('category');

        if ($category instanceof DocumentCategory && is_numeric($value) && in_array((int) $value, resolve(CategoryTree::class)->withDescendants($category->id), true)) {
            $fail(__('A category cannot sit inside itself or inside one of its subcategories.'));
        }
    }
}
