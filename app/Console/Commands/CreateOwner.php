<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Owner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates an owner account for the admin panel. The authenticator app is set up at the first login.
 */
#[Description('Create an owner account for the admin panel')]
#[Signature('owner:create')]
final class CreateOwner extends Command
{
    /**
     * Asks name, email and password, and creates the account.
     */
    public function handle(): int
    {
        $name = text('Name', required: true);
        $email = text('Email', required: true);
        $password = password('Password', required: true);
        $confirmation = password('Confirm the password', required: true);

        if (Owner::query()->where('email', $email)->exists()) {
            $this->error('This email is already used.');

            return self::FAILURE;
        }

        if ($password !== $confirmation) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        Owner::query()->create(['name' => $name, 'email' => $email, 'password' => $password]);
        $this->info('Owner created: the authenticator app is set up at the first login.');

        return self::SUCCESS;
    }
}
