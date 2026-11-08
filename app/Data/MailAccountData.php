<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An account of the family that can be told about the mail received, in *Settings → Address for the bills*.
 */
#[TypeScript]
final class MailAccountData extends Data
{
    /**
     * @param  int  $id  the id of the account
     * @param  string  $name  the name on the account
     * @param  string  $email  the email of the account
     * @param  bool  $chosen  whether the account gets the notice
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $chosen,
    ) {}

    /**
     * The account as the page shows it.
     *
     * @param  User  $user  the account
     * @param  list<int>|null  $notify  the accounts chosen, null when everyone is
     */
    public static function fromUser(User $user, ?array $notify): self
    {
        return new self($user->id, $user->name, $user->email, $notify === null || in_array($user->id, $notify, true));
    }
}
