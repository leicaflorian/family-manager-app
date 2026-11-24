<?php

declare(strict_types=1);

use App\Actions\Diagnostics\RecordServerError;
use App\Http\Middleware\AddRobotsHeader;
use App\Http\Middleware\ApplyInstallationSettings;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\BlockSuspendedFamily;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LimitLinkedDevice;
use App\Http\Middleware\LimitVisit;
use App\Http\Middleware\RecordLastSeen;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\SeparateHosts;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SignInLinkedDevice;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['sidebar_state']);

        // First, so that every log line of the request, even one of an early middleware, carries the id
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(AddRobotsHeader::class);

        $middleware->web(append: [
            SignInLinkedDevice::class,
            LimitVisit::class,
            LimitLinkedDevice::class,
            SetLocale::class,
            BlockSuspendedFamily::class,
            RecordLastSeen::class,
            ApplyInstallationSettings::class,
            HandleInertiaRequests::class,
            // At most 20 files in the Link header: with all of them a big page went over the room nginx keeps for
            // the headers and answered 502. The HTML still preloads every file.
            AddLinkHeadersForPreloadedAssets::using(20),
        ], prepend: [
            // First of the group: a page of the app on the host of the panel answers 404 before a session or a sign-in
            SeparateHosts::class,
        ]);

        $middleware->alias(['feature' => RequireFeature::class]);

        // Laravel sorts the middleware by priority and `auth` would run before this one: the phone must enter first
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: SignInLinkedDevice::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // No `stop()`: the error still goes to the log
        $exceptions->report(fn (Throwable $e) => resolve(RecordServerError::class)->handle($e));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
