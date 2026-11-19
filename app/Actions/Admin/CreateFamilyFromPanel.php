<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Families\CreateFamily;
use App\Enums\OwnerActionType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Localizable;

/**
 * *Create a family* of the owner panel: the registration is closed, so a new family enters from here. The first
 * account gets a password nobody knows; the panel then sends the welcome mail with the link to choose its own
 * ({@see SendWelcomeMail}), so it can tell when the mail does not go out.
 */
final readonly class CreateFamilyFromPanel
{
    use Localizable;

    /**
     * @param  CreateFamily  $createFamily  creates the family, its account and what it needs to start
     * @param  LogOwnerAction  $logOwnerAction  writes the line in the log of the owner
     */
    public function __construct(private CreateFamily $createFamily, private LogOwnerAction $logOwnerAction) {}

    /**
     * Creates the family with its `admin` account and writes the log, with the account on the line: the panel offers
     * the welcome mail again only to the account it created.
     *
     * The family is created in the language chosen, not in the one of the panel: the names of the starting
     * categories are translated while they are written.
     *
     * @param  string  $familyName  the name of the family
     * @param  string  $firstName  the first name of the first account
     * @param  string  $lastName  the last name of the first account
     * @param  string  $email  the email of the account, saved in lowercase
     * @param  string  $locale  the language of the family and of the account: `it` or `en`
     * @return User the `admin` account just created
     */
    public function handle(string $familyName, string $firstName, string $lastName, string $email, string $locale): User
    {
        return $this->withLocale($locale, fn (): User => DB::transaction(function () use ($familyName, $firstName, $lastName, $email, $locale): User {
            $user = $this->createFamily->handle($familyName, $firstName, $lastName, Str::lower($email), Str::password(64));
            $user->forceFill(['locale' => $locale])->save();
            $this->logOwnerAction->handle(OwnerActionType::FamilyCreated, $user->family, $user);

            return $user;
        }));
    }
}
