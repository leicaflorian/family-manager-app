<?php

declare(strict_types=1);

use App\Actions\Families\CreateFamily;
use App\Models\Family;
use App\Models\User;
use App\Support\CurrentFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * The Pest tests in Feature start from an empty database and use the app's
 * TestCase. The class-based tests of the starter kit declare what they need
 * on their own.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * The tests in Unit have the app booted too: those that use the database add
 * RefreshDatabase in their own file.
 */
pest()->extend(TestCase::class)
    ->in('Unit');

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

/**
 * Runs some work inside the Bianchi family.
 *
 * @template T
 *
 * @param  callable(): T  $work
 * @return T
 */
function insideBianchi(callable $work): mixed
{
    return CurrentFamily::using(Family::query()->where('name', 'Bianchi')->sole()->id, $work);
}
