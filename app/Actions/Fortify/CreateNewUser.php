<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Families\CreateFamily;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * The *Sign up* form: checks the data and registers a new family.
 */
final readonly class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @param  CreateFamily  $createFamily  the action that creates the family
     */
    public function __construct(private CreateFamily $createFamily) {}

    /**
     * Checks the data of the form and creates the family with its first account.
     *
     * The last name is also the name of the family: whoever registers is the
     * first of the family, and the family takes their name.
     *
     * @param  array<string, string>  $input  the fields of the form
     * @return User the `admin` account of the new family
     *
     * @throws ValidationException if a field is wrong
     */
    public function create(array $input): User
    {
        /** @var array{first_name: string, last_name: string, email: string, password: string} $data */
        $data = Validator::make($input, [
            'first_name' => $this->nameRules(),
            'last_name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRulesWithoutConfirmation(),
            'terms' => ['accepted'],
        ])->validate();

        return $this->createFamily->handle($data['last_name'], $data['first_name'], $data['last_name'], $data['email'], $data['password']);
    }
}
