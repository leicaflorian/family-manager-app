<?php

declare(strict_types=1);

use App\Services\Mailgun\MailgunRouteException;
use App\Services\Mailgun\MailgunRoutes;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const MG_KEY = 'key-test';
const MG_DOMAIN = 'mg.leicaflorianrobert.dev';
const MG_TARGET = 'https://family.leicaflorianrobert.dev/mail/mailgun';
const MG_APP_URL = 'https://family.leicaflorianrobert.dev';
const MG_WANTED = 'match_recipient(".*@mg\.leicaflorianrobert\.dev")';
const MG_OLD = 'match_recipient("bollette@mg.leicaflorianrobert.dev")';
const MG_OURS = 'forward("'.MG_TARGET.'")';
const MG_BASE = 'https://mailgun.test';

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('services.mailgun.api_key', MG_KEY);
    config()->set('services.mailgun.endpoint', MG_BASE);
});

/**
 * Fakes a Mailgun holding these routes: it answers the list by pages, and any write with $write.
 *
 * @param  list<array{id: string, expression: string, actions: list<string>}>  $routes
 * @param  array{status: int, body: mixed}  $write
 */
function fakeMailgun(array $routes, array $write = ['status' => 200, 'body' => ['message' => 'ok']]): void
{
    Http::fake(function (Request $request) use ($routes, $write) {
        if ($request->method() !== 'GET') {
            return Http::response($write['body'], $write['status']);
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $skip = (int) ($query['skip'] ?? 0);
        $limit = (int) ($query['limit'] ?? 100);

        return Http::response(['total_count' => count($routes), 'items' => array_slice($routes, $skip, $limit)]);
    });
}

/**
 * The writes sent so far, as "METHOD url".
 *
 * @return list<string>
 */
function mailgunWrites(): array
{
    $writes = [];

    foreach (Http::recorded() as [$request]) {
        if ($request->method() !== 'GET') {
            $writes[] = $request->method().' '.$request->url();
        }
    }

    return $writes;
}

/**
 * The first recorded write.
 */
function firstMailgunWrite(): Request
{
    foreach (Http::recorded() as [$request]) {
        if ($request->method() !== 'GET') {
            return $request;
        }
    }

    throw new RuntimeException('No write was sent.');
}

function routes(): MailgunRoutes
{
    return resolve(MailgunRoutes::class);
}

test('the target is the public address plus the door of Mailgun, only on https', function (): void {
    expect(MailgunRoutes::target('https://family.leicaflorianrobert.dev/'))->toBe(MG_TARGET)
        ->and(MailgunRoutes::target('https://family.leicaflorianrobert.dev'))->toBe(MG_TARGET)
        ->and(MailgunRoutes::target('HTTPS://Family.LeicaFlorianRobert.dev'))->toBe(MG_TARGET)
        ->and(MailgunRoutes::target('https://example.com/Famiglia/'))->toBe('https://example.com/Famiglia/mail/mailgun')
        ->and(MailgunRoutes::target('not an address'))->toBeNull()
        ->and(MailgunRoutes::target('http://localhost:8056'))->toBeNull()
        ->and(MailgunRoutes::target(''))->toBeNull();
});

test('no target for an https address that Mailgun cannot reach: a local domain or an IP', function (): void {
    expect(MailgunRoutes::target('https://local.familymanager.test'))->toBeNull()
        ->and(MailgunRoutes::target('https://Local.FamilyManager.TEST/'))->toBeNull()
        ->and(MailgunRoutes::target('https://familymanager.localhost'))->toBeNull()
        ->and(MailgunRoutes::target('https://localhost:8056'))->toBeNull()
        ->and(MailgunRoutes::target('https://family.local'))->toBeNull()
        ->and(MailgunRoutes::target('https://127.0.0.1'))->toBeNull()
        ->and(MailgunRoutes::target('https://192.168.1.20:8056'))->toBeNull()
        ->and(MailgunRoutes::target('https://[::1]'))->toBeNull()
        ->and(MailgunRoutes::target('https://test.example.com'))->toBe('https://test.example.com/mail/mailgun');
});

test('with no route, one is created for the whole domain', function (): void {
    fakeMailgun([]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('created');

    expect(mailgunWrites())->toBe(['POST '.MG_BASE.'/v3/routes']);
    $post = firstMailgunWrite();
    expect($post->header('Content-Type'))->toBe(['application/x-www-form-urlencoded'])
        ->and($post->data()['priority'])->toBe('0')
        ->and($post->data()['description'])->toBe('Family Manager - posta in arrivo')
        ->and($post->data()['expression'])->toBe(MG_WANTED)
        ->and($post->data()['action'])->toBe(MG_OURS);
});

test('a route of ours with another filter gets only the new filter', function (): void {
    fakeMailgun([['id' => 'r1', 'expression' => MG_OLD, 'actions' => [MG_OURS]]]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('updated');

    expect(mailgunWrites())->toBe(['PUT '.MG_BASE.'/v3/routes/r1'])
        ->and(firstMailgunWrite()->data())->toBe(['expression' => MG_WANTED]);
});

test('a route written by hand with other quotes or a final slash is still ours', function (): void {
    fakeMailgun([['id' => 'r1', 'expression' => MG_OLD, 'actions' => ["forward('".MG_TARGET."/')", 'stop()']]]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('updated')
        ->and(mailgunWrites())->toBe(['PUT '.MG_BASE.'/v3/routes/r1']);
});

test('a route written by hand with capitals in the host is still ours', function (): void {
    fakeMailgun([['id' => 'r1', 'expression' => MG_OLD, 'actions' => ['forward("https://Family.LeicaFlorianRobert.dev/mail/mailgun")']]]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('updated')
        ->and(mailgunWrites())->toBe(['PUT '.MG_BASE.'/v3/routes/r1']);
});

test('a route that forwards to another path of this installation is not ours', function (): void {
    fakeMailgun([['id' => 'r1', 'expression' => MG_OLD, 'actions' => ['forward("https://family.leicaflorianrobert.dev/mail/Mailgun")']]]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('created')
        ->and(mailgunWrites())->toBe(['POST '.MG_BASE.'/v3/routes']);
});

test('exactly a hundred routes are read with one more call, and ours is changed once', function (): void {
    $others = array_map(fn (int $i): array => ['id' => "o{$i}", 'expression' => MG_OLD, 'actions' => ['forward("https://altro.example/hook")']], range(0, 98));
    fakeMailgun([...$others, ['id' => 'r1', 'expression' => MG_OLD, 'actions' => [MG_OURS]]]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('updated')
        ->and(mailgunWrites())->toBe(['PUT '.MG_BASE.'/v3/routes/r1']);
});

test('a Mailgun that gives the same page again and again changes ours once and stops', function (): void {
    $page = array_map(fn (int $i): array => ['id' => "r{$i}", 'expression' => $i !== 0 ? MG_OLD : MG_WANTED, 'actions' => $i === 1 ? [MG_OURS] : []], range(0, 99));
    Http::fake(fn (Request $request) => Http::response($request->method() === 'PUT' ? ['message' => 'ok'] : ['items' => $page]));

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('updated');

    $methods = [];
    foreach (Http::recorded() as [$request]) {
        $methods[] = $request->method();
    }
    expect($methods)->toBe(['GET', 'GET', 'PUT']);
});

test('more routes than fifty pages refuse the save', function (): void {
    $next = 0;
    Http::fake(function () use (&$next) {
        $items = [];
        for ($i = 0; $i < 100; $i++) {
            $items[] = ['id' => 'r'.$next++, 'expression' => MG_OLD, 'actions' => []];
        }

        return Http::response(['items' => $items]);
    });

    expect(fn (): string => routes()->sync(MG_DOMAIN, MG_APP_URL))
        ->toThrow(MailgunRouteException::class, 'Mailgun did not give the routes: there are too many.');
    Http::assertSentCount(50);
});

test('every call has a time limit and the key as Basic auth', function (): void {
    fakeMailgun([['id' => 'r1', 'expression' => MG_OLD, 'actions' => [MG_OURS]]]);

    routes()->sync(MG_DOMAIN, MG_APP_URL);

    $sent = 0;
    foreach (Http::recorded() as [$request]) {
        $sent++;
        expect($request->header('Authorization'))->toBe(['Basic '.base64_encode('api:'.MG_KEY)]);
    }
    expect($sent)->toBeGreaterThan(1);
});

test('a route of ours with the right filter is left as it is', function (): void {
    fakeMailgun([['id' => 'r1', 'expression' => MG_WANTED, 'actions' => [MG_OURS]]]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('unchanged')
        ->and(mailgunWrites())->toBe([]);
});

test('two routes of ours are both changed, none is deleted', function (): void {
    fakeMailgun([
        ['id' => 'r1', 'expression' => MG_OLD, 'actions' => [MG_OURS]],
        ['id' => 'r2', 'expression' => 'catch_all()', 'actions' => [MG_OURS]],
    ]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('updated')
        ->and(mailgunWrites())->toBe(['PUT '.MG_BASE.'/v3/routes/r1', 'PUT '.MG_BASE.'/v3/routes/r2']);
});

test('a route that forwards elsewhere is never touched', function (): void {
    fakeMailgun([['id' => 'other', 'expression' => MG_OLD, 'actions' => ['forward("https://altro.example/hook")']]]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('created')
        ->and(mailgunWrites())->toBe(['POST '.MG_BASE.'/v3/routes']);
});

test('a route of another project stays as it is, even when it matches the same domain', function (): void {
    fakeMailgun([['id' => 'other', 'expression' => MG_WANTED, 'actions' => ['forward("https://altro.example/hook")', 'stop()']]]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('created')
        ->and(mailgunWrites())->toBe(['POST '.MG_BASE.'/v3/routes']);
});

test('a route of ours after the first hundred is found', function (): void {
    $others = array_map(fn (int $i): array => ['id' => "o{$i}", 'expression' => MG_OLD, 'actions' => ['forward("https://altro.example/hook")']], range(0, 99));
    fakeMailgun([...$others, ['id' => 'r1', 'expression' => MG_OLD, 'actions' => [MG_OURS]]]);

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('updated')
        ->and(mailgunWrites())->toBe(['PUT '.MG_BASE.'/v3/routes/r1']);
});

test('with no route Mailgun gives items null and a total of zero: one is created', function (): void {
    Http::fake(fn (Request $request) => Http::response($request->method() === 'POST' ? ['message' => 'ok'] : ['total_count' => 0, 'items' => null]));

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('created')
        ->and(mailgunWrites())->toBe(['POST '.MG_BASE.'/v3/routes']);
});

test('an answer to the list that is not a list refuses the save, and nothing is created', function (mixed $body): void {
    Http::fake(fn () => Http::response($body));

    expect(fn (): string => routes()->sync(MG_DOMAIN, MG_APP_URL))
        ->toThrow(MailgunRouteException::class, 'Mailgun did not give the routes: answer not recognised');
    Http::assertSentCount(1);
})->with([
    'html' => ['<html>maintenance</html>'],
    'no items' => [['total_count' => 0]],
    'items a string' => [['items' => 'none']],
    'items null' => [['items' => null]],
    'items null, total 3' => [['total_count' => 3, 'items' => null]],
]);

test('a route of ours after the first hundred is found without the total', function (): void {
    $others = array_map(fn (int $i): array => ['id' => "o{$i}", 'expression' => MG_OLD, 'actions' => ['forward("https://altro.example/hook")']], range(0, 99));
    $all = [...$others, ['id' => 'r1', 'expression' => MG_OLD, 'actions' => [MG_OURS]]];
    Http::fake(function (Request $request) use ($all) {
        if ($request->method() !== 'GET') {
            return Http::response(['message' => 'ok']);
        }
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response(['items' => array_slice($all, (int) $query['skip'], 100)]);
    });

    expect(routes()->sync(MG_DOMAIN, MG_APP_URL))->toBe('updated');

    $methods = [];
    foreach (Http::recorded() as [$request]) {
        $methods[] = $request->method();
    }
    expect($methods)->toBe(['GET', 'GET', 'PUT']);
});

test('a refused key refuses the save', function (): void {
    Http::fake(fn () => Http::response('Forbidden', 401));

    expect(fn (): string => routes()->sync(MG_DOMAIN, MG_APP_URL))
        ->toThrow(MailgunRouteException::class, 'Mailgun refuses the API key: it needs an account key, not a sending key.');
});

test('a refusal that is not about the key says what Mailgun said too', function (): void {
    Http::fake(fn () => Http::response(['message' => 'IP not allowed for '.MG_KEY], 403));

    try {
        routes()->sync(MG_DOMAIN, MG_APP_URL);
        $this->fail('The sync should have thrown.');
    } catch (MailgunRouteException $e) {
        expect($e->getMessage())->toBe('Mailgun refuses the API key: it needs an account key, not a sending key. Mailgun says: IP not allowed for …');
    }
});

test('when the second change is refused the first is done and the save is refused', function (): void {
    $done = [];
    Http::fake(function (Request $request) use (&$done) {
        if ($request->method() !== 'PUT') {
            return Http::response(['total_count' => 2, 'items' => [
                ['id' => 'r1', 'expression' => MG_OLD, 'actions' => [MG_OURS]],
                ['id' => 'r2', 'expression' => MG_OLD, 'actions' => [MG_OURS]],
            ]]);
        }
        $done[] = $request->url();

        return count($done) === 1 ? Http::response(['message' => 'ok']) : Http::response(['message' => 'broken'], 500);
    });

    expect(fn (): string => routes()->sync(MG_DOMAIN, MG_APP_URL))
        ->toThrow(MailgunRouteException::class, 'Mailgun did not change the route: broken');
    expect($done)->toBe([MG_BASE.'/v3/routes/r1', MG_BASE.'/v3/routes/r2']);
});

test('a refused creation says what Mailgun said', function (): void {
    fakeMailgun([], ['status' => 400, 'body' => ['message' => 'Route limit reached']]);

    expect(fn (): string => routes()->sync(MG_DOMAIN, MG_APP_URL))
        ->toThrow(MailgunRouteException::class, 'Mailgun did not create the route: Route limit reached');
});

test('a Mailgun that does not answer refuses the save', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28 with '.MG_KEY));

    try {
        routes()->sync(MG_DOMAIN, MG_APP_URL);
        $this->fail('The sync should have thrown.');
    } catch (MailgunRouteException $e) {
        expect($e->getMessage())->toBe('Mailgun does not answer: try again in a moment.')
            ->and($e->getMessage())->not->toContain(MG_KEY);
    }
});

test('no error message has the key in it', function (): void {
    $failures = [
        fn () => fakeMailgun([], ['status' => 400, 'body' => ['message' => 'no, '.MG_KEY]]),
        fn () => fakeMailgun([['id' => 'r1', 'expression' => MG_OLD, 'actions' => [MG_OURS]]], ['status' => 500, 'body' => ['message' => 'oops '.MG_KEY]]),
        fn () => Http::fake(fn () => Http::response(['message' => 'bad key '.MG_KEY], 401)),
        fn () => Http::fake(fn () => Http::response(['message' => 'denied '.MG_KEY], 403)),
    ];

    foreach ($failures as $fail) {
        $fail();
        try {
            routes()->sync(MG_DOMAIN, MG_APP_URL);
            $this->fail('The sync should have thrown.');
        } catch (MailgunRouteException $e) {
            expect($e->getMessage())->not->toContain(MG_KEY);
        }
    }
});

describe('mail-inbox:route', function (): void {
    beforeEach(function (): void {
        config()->set('services.mailgun.inbox_domain', MG_DOMAIN);
        config()->set('app.url', MG_APP_URL);
    });

    test('it creates the route and says so', function (): void {
        fakeMailgun([]);

        $this->artisan('mail-inbox:route')->expectsOutputToContain('created')->assertSuccessful();
        expect(mailgunWrites())->toBe(['POST '.MG_BASE.'/v3/routes']);
    });

    test('without a key it stops with a message and calls nothing', function (): void {
        config()->set('services.mailgun.api_key');
        Http::fake();

        $this->artisan('mail-inbox:route')->expectsOutputToContain('MAILGUN_API_KEY')->assertFailed();
        Http::assertNothingSent();
    });

    test('without a domain it stops with a message and calls nothing', function (): void {
        config()->set('services.mailgun.inbox_domain', '');
        Http::fake();

        $this->artisan('mail-inbox:route')->expectsOutputToContain('MAIL_INBOX_DOMAIN')->assertFailed();
        Http::assertNothingSent();
    });

    test('with an address Mailgun cannot reach it stops with a message and calls nothing', function (): void {
        config()->set('app.url', 'http://localhost:8090');
        Http::fake();

        $this->artisan('mail-inbox:route')->expectsOutputToContain('APP_URL')->assertFailed();
        Http::assertNothingSent();
    });

    test('a refusal is said without the key in the output', function (): void {
        Http::fake(fn () => Http::response(['message' => 'denied '.MG_KEY], 403));

        $this->artisan('mail-inbox:route')
            ->expectsOutputToContain('Mailgun refuses the API key')
            ->doesntExpectOutputToContain(MG_KEY)
            ->assertFailed();
    });
});
