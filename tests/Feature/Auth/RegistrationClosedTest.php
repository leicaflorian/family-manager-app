<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Family;
use App\Models\Invitation;
use App\Models\Person;
use App\Models\User;
use App\Support\PageMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sign-up closed by `REGISTRATION_OPEN=false`: no *Sign up* page or post, but invitations keep working.
 */
final class RegistrationClosedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Boots the application again with sign-up open or closed: Fortify reads its features and registers its routes at boot.
     *
     * @param  bool  $open  whether sign-up is open
     */
    private function bootWithRegistration(bool $open): void
    {
        putenv('REGISTRATION_OPEN='.($open ? 'true' : 'false'));
        $_ENV['REGISTRATION_OPEN'] = $_SERVER['REGISTRATION_OPEN'] = $open ? 'true' : 'false';
        $this->refreshApplication();
        // The new application has a new connection, outside the transaction `RefreshDatabase` opened on the old one
        $this->beginDatabaseTransaction();
    }

    protected function tearDown(): void
    {
        putenv('REGISTRATION_OPEN');
        unset($_ENV['REGISTRATION_OPEN'], $_SERVER['REGISTRATION_OPEN']);

        parent::tearDown();
    }

    public function test_closed_sign_up_hides_the_register_page(): void
    {
        $this->bootWithRegistration(false);

        $this->get('/register')->assertNotFound();
    }

    public function test_closed_sign_up_refuses_a_register_post(): void
    {
        $this->bootWithRegistration(false);

        $this->post('/register', [
            'name' => 'Nuovo Utente',
            'family_name' => 'Nuovi',
            'email' => 'nuovo@example.com',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
        ])->assertNotFound();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Family::query()->count());
    }

    public function test_closed_sign_up_still_accepts_an_invitation(): void
    {
        $this->bootWithRegistration(false);
        $family = Family::factory()->create(['name' => 'Bianchi']);
        $laura = Person::factory()->for($family)->create(['first_name' => 'Laura', 'last_name' => 'Bianchi']);
        $invitation = Invitation::factory()->for($family)->create(['email' => 'laura.bianchi@example.com', 'person_id' => $laura->id, 'role' => 'member']);

        $this->post(route('invitation.store', $invitation->token), ['password' => 'a-long-password'])
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'laura.bianchi@example.com')->sole();
        $this->assertSame($family->id, $user->family_id);
    }

    public function test_closed_sign_up_keeps_register_out_of_the_indexable_pages(): void
    {
        $this->bootWithRegistration(false);

        $this->assertSame(['login'], PageMeta::indexable());
        $this->get('/login')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_open_sign_up_is_unchanged(): void
    {
        $this->bootWithRegistration(true);

        $this->get('/register')->assertOk();
        $this->assertSame(['login', 'register'], PageMeta::indexable());
    }
}
