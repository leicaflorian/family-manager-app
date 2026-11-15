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
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // *Enter as…* is opened from the admin panel: an owner who is not signed in goes to the sign-in of the panel
        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->routeIs('support.visit.start') ? route('filament.admin.auth.login') : route('login'),
        );

        // Laravel sorts the middleware by priority and `auth` would run before this one: the phone must enter first
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: SignInLinkedDevice::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // No `stop()`: the error still goes to the log
        $exceptions->report(fn (Throwable $e) => app(RecordServerError::class)->handle($e));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
