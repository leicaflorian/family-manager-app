<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Middleware\LimitVisit;
use App\Http\Middleware\SeparateHosts;
use App\Models\Family;
use App\Models\Owner;
use App\Models\User;
use App\Support\VisitHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The owner panel on its own host (`ADMIN_DOMAIN`): only the panel answers there, and the panel does not answer on
 * the host of the app. Without `ADMIN_DOMAIN` the panel stays at `/admin`.
 */
final class AdminHostTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMIN = 'https://admin.fameja.app';

    private const string APP = 'https://my.fameja.app';

    /**
     * Boots the application again with the hosts of production: Filament registers the routes of the panel at boot,
     * from the domain read when the panel is configured.
     */
    private function bootWithAdminDomain(): void
    {
        $this->setEnv('ADMIN_DOMAIN', 'admin.fameja.app');
        $this->setEnv('APP_URL', self::APP);
        // A new repository: the old one remembers it loaded APP_URL from `.env` and would write it again over ours
        Env::enablePutenv();
        $this->refreshApplication();
        // The new application has a new connection, outside the transaction `RefreshDatabase` opened on the old one
        $this->beginDatabaseTransaction();
    }

    /**
     * Sets an environment variable everywhere Laravel reads it.
     *
     * @param  string  $name  the variable
     * @param  string  $value  its value
     */
    private function setEnv(string $name, string $value): void
    {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    protected function tearDown(): void
    {
        foreach (['ADMIN_DOMAIN', 'APP_URL'] as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
        Env::enablePutenv();

        parent::tearDown();
    }

    /**
     * The session cookie a response sets.
     *
     * @param  TestResponse<Response>  $response  the response
     */
    private function sessionCookie(TestResponse $response): Cookie
    {
        $name = config('session.cookie');
        $cookie = collect($response->headers->getCookies())->first(fn (Cookie $cookie): bool => $cookie->getName() === $name);
        $this->assertInstanceOf(Cookie::class, $cookie);

        return $cookie;
    }

    public function test_the_panel_answers_on_the_admin_host(): void
    {
        $this->bootWithAdminDomain();

        $this->get(self::ADMIN.'/')->assertRedirect(self::ADMIN.'/login');
        $this->get(self::ADMIN.'/login')->assertOk()->assertSeeHtml('livewire')->assertDontSeeHtml('data-page');
    }

    public function test_the_app_pages_do_not_answer_on_the_admin_host(): void
    {
        $this->bootWithAdminDomain();

        $this->get(self::ADMIN.'/dashboard')->assertNotFound();
        $this->get(self::ADMIN.'/register')->assertNotFound();
        $this->get(self::ADMIN.'/forgot-password')->assertNotFound();
        $this->post(self::ADMIN.'/mail/mailgun')->assertNotFound();
        $this->get(self::ADMIN.'/calendar/some-token.ics')->assertNotFound();
        $this->post(self::ADMIN.'/scan/share')->assertNotFound();
        $this->get(self::ADMIN.'/sw.js')->assertNotFound();
    }

    public function test_the_panel_does_not_answer_on_the_app_host(): void
    {
        $this->bootWithAdminDomain();

        $this->get(self::APP.'/admin')->assertNotFound();
        $this->get(self::APP.'/login')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('auth/Login'));
    }

    public function test_the_links_read_by_programs_point_to_the_app_host(): void
    {
        $this->bootWithAdminDomain();

        $this->assertStringStartsWith(self::APP.'/', route('calendar.feed', 'some-token'));
        $this->assertStringStartsWith(self::APP.'/', route('scan.share'));
        $this->assertStringStartsWith(self::ADMIN.'/', route('filament.admin.auth.login'));
    }

    public function test_an_owner_signs_in_on_the_admin_host_only(): void
    {
        $this->bootWithAdminDomain();
        $owner = Owner::factory()->withAuthenticator()->create();

        // The session cookie is host-only: no `SESSION_DOMAIN`, so it is never sent to the host of the app
        $this->assertNull(config('session.domain'));
        $this->assertNull($this->sessionCookie($this->get(self::ADMIN.'/login'))->getDomain());

        $response = $this->actingAs($owner, 'owner')->get(self::ADMIN.'/');
        $response->assertOk();
        $this->assertNull($this->sessionCookie($response)->getDomain());
    }

    /**
     * A family that lets support in, with one account.
     */
    private function familyUser(): User
    {
        $family = Family::factory()->create(['support_until' => now()->addDay()]);

        return User::factory()->create(['family_id' => $family->id]);
    }

    /**
     * Forgets the session and who is signed in, as the browser does going to the other host (cookies are host-only).
     */
    private function otherHost(): void
    {
        $this->flushSession();
        Auth::forgetGuards();
        Auth::shouldUse('web');
    }

    public function test_enter_as_goes_from_the_panel_host_to_the_app_host(): void
    {
        $this->bootWithAdminDomain();
        $owner = Owner::factory()->withAuthenticator()->create();
        $user = $this->familyUser();
        $enterAs = URL::signedRoute('filament.admin.enter-as', ['user' => $user->id]);

        $this->assertStringStartsWith(self::ADMIN.'/enter-as/', $enterAs);
        // The page of the family drawn first, then the click: the link points to the app all the same
        $this->actingAs($owner, 'owner')->get(self::ADMIN.'/')->assertOk();
        $link = $this->get($enterAs)->assertRedirect()->headers->get('Location');
        $this->assertIsString($link);
        $this->assertStringStartsWith(self::APP.'/support/visit/'.$user->id.'?', $link);

        // Opened on the host of the panel it is 404, and on the host of the app it starts the visit, with no session
        $this->get(self::ADMIN.parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY))->assertNotFound();
        $this->otherHost();
        $this->get($link)->assertRedirect(self::APP.'/dashboard');
        $this->assertSame($user->id, Auth::guard('web')->id());
        $this->assertSame($owner->id, Auth::guard('owner')->id());

        // Leaving signs the owner out of the session of the app: the panel keeps its own
        $this->post(self::APP.'/visit/leave')->assertRedirect(self::ADMIN);
        $this->assertFalse(Auth::guard('owner')->check());
    }

    public function test_the_redirect_of_enter_as_is_not_on_the_app_host(): void
    {
        $this->bootWithAdminDomain();
        $owner = Owner::factory()->withAuthenticator()->create();
        $user = $this->familyUser();
        $path = parse_url(URL::signedRoute('filament.admin.enter-as', ['user' => $user->id]));

        $this->actingAs($owner, 'owner')->get(self::APP.'/enter-as/'.$user->id.'?'.$path['query'])->assertNotFound();
    }

    public function test_a_visit_that_ends_by_itself_signs_the_owner_out_of_the_app(): void
    {
        $this->bootWithAdminDomain();
        $owner = Owner::factory()->withAuthenticator()->create();
        $user = $this->familyUser();

        URL::setRequest(Request::create(self::ADMIN.'/'));
        $this->get(VisitHandoff::link($owner, $user))->assertRedirect(self::APP.'/dashboard');
        $this->assertSame($owner->id, Auth::guard('owner')->id());

        $this->travel(LimitVisit::MINUTES)->minutes();
        $this->get(self::APP.'/dashboard')->assertRedirect(self::ADMIN);

        $this->assertFalse(Auth::guard('web')->check());
        $this->assertFalse(Auth::guard('owner')->check());
    }

    public function test_the_admin_host_is_recognised(): void
    {
        $this->bootWithAdminDomain();

        $this->assertTrue(SeparateHosts::onAdminHost(Request::create(self::ADMIN.'/login')));
        $this->assertFalse(SeparateHosts::onAdminHost(Request::create(self::APP.'/login')));
    }

    public function test_without_admin_domain_the_panel_stays_at_admin(): void
    {
        $this->assertNull(config('app.admin_domain'));
        $this->assertFalse(SeparateHosts::onAdminHost(Request::create('http://localhost/admin')));

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/login')->assertOk();
    }
}
