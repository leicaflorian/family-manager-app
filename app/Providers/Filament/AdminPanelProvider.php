<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use App\Models\Owner;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The admin panel of the owners: `/admin`, the `owner` guard, password and authenticator app (required, with
 * recovery codes, everywhere but in the local environment). Resources and pages are discovered in `app/Filament/`.
 */
final class AdminPanelProvider extends PanelProvider
{
    /**
     * An owner may do everything in the panel: the policies of the app are written for the accounts of a family
     * and would fail on an `Owner`. Only owners reach the panel (`canAccessPanel`), and the panel never shows content.
     */
    public function boot(): void
    {
        Gate::before(static fn (Authenticatable $user): ?bool => $user instanceof Owner ? true : null);
    }

    /**
     * Whether the owner needs the authenticator app: always, but not in the local environment, where the panel is
     * tried on the Mac with the test owner.
     */
    public static function requiresAuthenticator(): bool
    {
        return ! app()->isLocal();
    }

    /**
     * Configures the panel.
     *
     * @param  Panel  $panel  The panel to configure.
     */
    public function panel(Panel $panel): Panel
    {
        if (self::requiresAuthenticator()) {
            $panel->multiFactorAuthentication(AppAuthentication::make()->recoverable(), isRequired: true);
        }

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('owner')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
