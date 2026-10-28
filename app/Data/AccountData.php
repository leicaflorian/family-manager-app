<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * My account, as *Settings* and *My account* show it.
 */
#[TypeScript]
final class AccountData extends Data
{
    /**
     * @param  string  $first_name  the first name, from the person of the account
     * @param  string  $last_name  the last name
     * @param  string  $email  the email to sign in with
     * @param  string  $role  the level, "Administrator" or "Family member"
     * @param  bool  $is_admin  whether it can change the family settings
     * @param  string  $initials  the initials of the circle, when there is no photo
     * @param  string|null  $photo  the address of the photo
     * @param  bool  $is_last_admin  whether it is the only admin, who cannot delete themself
     * @param  string  $family  the name of the family
     */
    public function __construct(
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $email,
        public readonly string $role,
        public readonly bool $is_admin,
        public readonly string $initials,
        public readonly ?string $photo,
        public readonly bool $is_last_admin,
        public readonly string $family,
    ) {}

    /**
     * The data of an account. Without a person, the name comes from `users.name`.
     *
     * @param  User  $user  the account
     */
    public static function fromUser(User $user): self
    {
        $person = $user->person;

        return new self(
            first_name: $person->first_name ?? $user->name,
            last_name: $person->last_name ?? '',
            email: $user->email,
            role: $user->role->title(),
            is_admin: $user->isAdmin(),
            initials: $person->initials ?? mb_strtoupper(mb_substr($user->name, 0, 1)),
            photo: $person?->photoUrl(),
            is_last_admin: $user->isLastAdmin(),
            family: $user->family->name,
        );
    }
}
