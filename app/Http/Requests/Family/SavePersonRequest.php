<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Enums\PersonRole;
use App\Http\Requests\Concerns\UploadsImage;
use App\Models\Person;
use App\Support\ImageUpload;
use App\Support\Initials;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The form of a person, new or changed (only an `admin`).
 */
final class SavePersonRequest extends FormRequest
{
    use UploadsImage;

    /** The columns of the person the form fills. */
    private const array FIELDS = ['first_name', 'last_name', 'initials', 'role', 'birth_date', 'tax_code', 'notes'];

    /**
     * A new person whoever adds people, a change whoever may change them.
     */
    public function authorize(): bool
    {
        $person = $this->route('person');

        return $person instanceof Person
            ? $this->user()?->can('update', $person) ?? false
            : $this->user()?->can('create', Person::class) ?? false;
    }

    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'initials' => ['nullable', 'string', 'max:3'],
            'role' => ['required', Rule::enum(PersonRole::class)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'tax_code' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', ...ImageUpload::RULES],
            'remove_photo' => ['boolean'],
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
            'photo' => __('photo'),
            'first_name' => __('first name'),
            'last_name' => __('last name'),
            'initials' => __('initials'),
            'role' => __('role'),
            'birth_date' => __('birth date'),
            'tax_code' => __('tax code'),
        ];
    }

    /**
     * The validated columns of the person. Without initials, they come from the name.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = $this->safe()->only(self::FIELDS);

        if (! $this->filled('initials')) {
            $fields['initials'] = Initials::of($this->string('first_name')->toString(), $this->string('last_name')->toString());
        }

        return $fields;
    }
}
