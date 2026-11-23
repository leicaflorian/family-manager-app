<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\FamilySetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *Settings → Family name*: the new name of the family. Only whoever may change the settings of the family, an
 * `admin`, saves it. The name does not need to be unique: families are told apart by id.
 */
final class UpdateFamilyNameRequest extends FormRequest
{
    /**
     * Only whoever may change the settings of the family, an `admin`.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', FamilySetting::current());
    }

    /**
     * A name, trimmed by the middleware of Laravel, not empty and at most 255 characters.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => __('family name')];
    }

    /**
     * The name, checked.
     */
    public function name(): string
    {
        return $this->string('name')->toString();
    }
}
