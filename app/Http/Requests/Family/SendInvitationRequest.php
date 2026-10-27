<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Enums\PersonRole;
use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use App\Rules\OfFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The *Invite a family member* form: the email, who it is (an existing person
 * or a new one) and what they can do.
 */
final class SendInvitationRequest extends FormRequest
{
    /**
     * Only an `admin` invites.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
    }

    /**
     * The rules of the fields. Without `person_id` the first and last name of the new person are needed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::enum(Role::class)],
            // A person of the family who has no account yet.
            'person_id' => ['nullable', 'integer', new OfFamily('people'), Rule::exists('people', 'id')->whereNull('user_id')],
            'first_name' => ['required_without:person_id', 'nullable', 'string', 'max:255'],
            'last_name' => ['required_without:person_id', 'nullable', 'string', 'max:255'],
            'person_role' => ['nullable', Rule::enum(PersonRole::class)],
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
            'email' => __('email'),
            'role' => __('what they can do'),
            'person_id' => __('who it is'),
            'first_name' => __('first name'),
            'last_name' => __('last name'),
            'person_role' => __('in the family they are'),
        ];
    }
}
