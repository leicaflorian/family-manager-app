<?php

declare(strict_types=1);

use App\Actions\Families\CreateFamily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * I test di Pest in Feature partono da un database vuoto e usano il TestCase
 * dell'app. I test a classi dello starter kit dichiarano già da soli quello
 * che serve.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Anche i test in Unit hanno l'app avviata: chi usa il database aggiunge
 * RefreshDatabase nel proprio file.
 */
pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Iscrive una famiglia come fa l'app (con le categorie di partenza), già accolta
 * dal *Benvenuto*, e ne restituisce l'admin, con l'email confermata.
 */
/**
 * Registers a family through `CreateFamily`, with the email verified and the
 * *Welcome* done: its `admin` account is ready to use the app.
 *
 * @param  string  $lastName  the last name, which is also the name of the family
 * @param  string  $email  the email of the `admin` account
 */
function registerFamily(string $lastName = 'Bianchi', string $email = 'marco@example.com'): User
{
    $admin = resolve(CreateFamily::class)->handle($lastName, 'Marco', $lastName, $email, 'a-long-password');
    $admin->forceFill(['email_verified_at' => now()])->save();
    $admin->family->update(['welcomed_at' => now()]);

    return $admin;
}
