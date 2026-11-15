<?php

declare(strict_types=1);

use App\Models\Owner;
use App\Models\User;
use App\Support\VisitHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\travel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.url' => 'https://my.fameja.app']);
    // The link is made by the panel, on its own host
    URL::setRequest(Request::create('http://admin.fameja.app/families/1'));

    $this->owner = (new Owner)->forceFill(['id' => 7]);
    $this->user = (new User)->forceFill(['id' => 12]);
});

/**
 * The token a link of *Enter as* carries.
 */
function handoffToken(string $link): string
{
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

    expect($query['token'] ?? null)->toBeString()->toHaveLength(40);

    return $query['token'];
}

test('link points to the app host', function (): void {
    $link = VisitHandoff::link($this->owner, $this->user);

    expect($link)->toStartWith('https://my.fameja.app/support/visit/12?')
        ->toContain('signature=')
        ->toContain('expires=')
        // The generator of the panel is left as it was
        ->and(url('/x'))->toBe('http://admin.fameja.app/x');
});

test('link points to the app host after the panel wrote its own links', function (): void {
    URL::setRequest(Request::create('https://admin.fameja.app/families/1'));
    // The panel draws its page first: the generator of the request has written routes with its own root
    route('dashboard');
    url()->route('support.visit.start', ['user' => 12]);

    expect(VisitHandoff::link($this->owner, $this->user))->toStartWith('https://my.fameja.app/support/visit/12?')
        ->and(route('dashboard'))->toStartWith('https://admin.fameja.app/');
});

test('without a scheme the app url is taken as https', function (): void {
    config(['app.url' => 'my.fameja.app']);

    expect(VisitHandoff::link($this->owner, $this->user))->toStartWith('https://my.fameja.app/support/visit/12?');
});

test('consume returns the owner once', function (): void {
    $owner = Owner::factory()->create();
    $token = handoffToken(VisitHandoff::link($owner, $this->user));

    expect(VisitHandoff::consume($token, 12)?->id)->toBe($owner->id)
        ->and(VisitHandoff::consume($token, 12))->toBeNull();
});

test('consume refuses another user', function (): void {
    $owner = Owner::factory()->create();
    $token = handoffToken(VisitHandoff::link($owner, $this->user));

    expect(VisitHandoff::consume($token, 13))->toBeNull()
        ->and(VisitHandoff::consume($token, 12))->toBeNull();
});

test('consume gives nothing while another opening holds the token', function (): void {
    $owner = Owner::factory()->create();
    $token = handoffToken(VisitHandoff::link($owner, $this->user));

    $lock = Cache::lock('visit-handoff:lock:'.$token, 5);
    expect($lock->get())->toBeTrue()
        ->and(VisitHandoff::consume($token, 12))->toBeNull();
    $lock->release();
});

test('the entry expires after 60 seconds', function (): void {
    $owner = Owner::factory()->create();
    $token = handoffToken(VisitHandoff::link($owner, $this->user));

    travel(61)->seconds();

    expect(VisitHandoff::consume($token, 12))->toBeNull();
});
