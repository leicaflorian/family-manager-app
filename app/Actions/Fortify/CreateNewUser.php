<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Famiglie\CreaFamiglia;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Il modulo *Iscriviti*: controlla i dati e iscrive una famiglia nuova.
 */
final readonly class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @param  CreaFamiglia  $creaFamiglia  l'azione che crea la famiglia
     */
    public function __construct(private CreaFamiglia $creaFamiglia) {}

    /**
     * Controlla i dati del modulo e crea la famiglia con il suo primo account.
     *
     * Il cognome è anche il nome della famiglia: chi si iscrive è il primo
     * della famiglia, e la famiglia si chiama come lui.
     *
     * @param  array<string, string>  $input  i campi del modulo
     * @return User l'account `admin` della famiglia nuova
     *
     * @throws ValidationException se un campo non va
     */
    public function create(array $input): User
    {
        /** @var array{nome: string, cognome: string, email: string, password: string} $dati */
        $dati = Validator::make($input, [
            'nome' => $this->nameRules(),
            'cognome' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRulesWithoutConfirmation(),
            'condizioni' => ['accepted'],
        ])->validate();

        return $this->creaFamiglia->handle($dati['cognome'], $dati['nome'], $dati['cognome'], $dati['email'], $dati['password']);
    }
}
