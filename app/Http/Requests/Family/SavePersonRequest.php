<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Concerns\PersonValidationRules;
use App\Enums\PersonRole;
use App\Http\Requests\Concerns\UploadsImage;
use App\Models\Person;
use App\Support\ImageUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The form of a person, new or changed (only an `admin`).
 */
final class SavePersonRequest extends FormRequest
{
    use PersonValidationRules;
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
            ...$this->personRules(),
            'initials' => ['nullable', 'string', 'max:3'],
            'role' => ['required', Rule::enum(PersonRole::class)],
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
     * The validated columns of the person (`SavePerson` fills in the initials).
     *
     * @return array{first_name: string, last_name: string, role: string, initials?: ?string, birth_date?: ?string, tax_code?: ?string, notes?: ?string}
     */
    public function fields(): array
    {
        /** @var array{first_name: string, last_name: string, role: string, initials?: ?string, birth_date?: ?string, tax_code?: ?string, notes?: ?string} */
        return $this->safe()->only(self::FIELDS);
    }
}
