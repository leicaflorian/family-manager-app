<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The form of an organization, new or changed (only an `admin`).
 */
final class SaveOrganizationRequest extends FormRequest
{
    /** The columns of the organization the form fills. */
    private const array FIELDS = ['name', 'types', 'email', 'phone', 'website', 'notes'];

    /**
     * A new organization whoever adds them, a change whoever may change it.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            ? $this->user()?->can('update', $organization) ?? false
            : $this->user()?->can('create', Organization::class) ?? false;
    }

    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'types' => ['nullable', 'array'],
            'types.*' => ['required', 'distinct', Rule::enum(OrganizationType::class)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('name'),
            'types.*' => __('type'),
            'email' => __('email'),
            'phone' => __('phone'),
            'website' => __('website'),
        ];
    }

    /**
     * The validated columns of the organization.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->safe()->only(self::FIELDS);
    }
}
