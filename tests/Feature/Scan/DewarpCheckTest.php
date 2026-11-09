<?php

declare(strict_types=1);

namespace Tests\Feature\Scan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The page that times *Raddrizza* on a phone: it exists only on the development machine.
 */
final class DewarpCheckTest extends TestCase
{
    use RefreshDatabase;

    /** The `APP_ENV` of the tests (`phpunit.xml`), given back after a test that boots as local. */
    private const string TESTING = 'testing';

    /**
     * Boots the application again as if it ran locally: routes are registered at boot, from the environment.
     * `tearDown()` gives `APP_ENV` back to the other tests.
     */
    private function bootLocally(): void
    {
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
        // Routes are registered at boot, so the application is booted again with the new environment
        $this->refreshApplication();
        // The new application has a new database connection, outside the transaction `RefreshDatabase` opened on the old one: without this, what the test writes stays in the database and breaks the tests that count rows (the seeder ones)
        $this->beginDatabaseTransaction();
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV='.self::TESTING);
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = self::TESTING;
        parent::tearDown();
    }

    public function test_the_page_does_not_exist_outside_local(): void
    {
        $this->assertFalse(app()->isLocal());
        $this->assertFalse(Route::has('scan.dewarp-check'));
        $this->actingAs(User::factory()->create())->get('/scan/dewarp-check')->assertNotFound();
    }

    public function test_a_guest_is_sent_to_the_login_page_locally(): void
    {
        $this->bootLocally();

        $this->get(route('scan.dewarp-check'))->assertRedirect(route('login'));
    }

    public function test_an_account_sees_the_page_locally(): void
    {
        $this->bootLocally();

        $this->actingAs(User::factory()->create())
            ->get(route('scan.dewarp-check'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('scan/DewarpCheck'));
    }
}
