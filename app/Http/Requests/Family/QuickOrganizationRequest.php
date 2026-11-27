<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The quick form of an organization inside a card form: name and types only
 * (whoever may add organizations). Errors go in the `organization` bag so they
 * do not mix with the ones of the card form.
 */
final class QuickOrganizationRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'organization';

    /**
     * Whoever may add an organization.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Organization::class) ?? false;
    }

    /**
     * The same rules for name and types as the full form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return SaveOrganizationRequest::nameAndTypesRules();
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => __('name'), 'types.*' => __('type')];
    }
}
