<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\DocumentReader;
use App\Listeners\LiftLinkedDeviceLimit;
use App\Models\Family;
use App\Models\FamilyModel;
use App\Models\User;
use App\Observers\LoanPlanObserver;
use App\Policies\FamilyPolicy;
use App\Services\Reading\AiDocumentReader;
use App\Support\CurrentFamily;
use App\Support\InstallationSettings;
use App\Support\Linkables;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request (and per test): a family set by hand does not carry over to the next one
        $this->app->scoped(CurrentFamily::class);

        // One per request too: the loan observer keeps it quiet while it writes the planned expense of a loan
        $this->app->scoped(LoanPlanObserver::class);

        // The documents are read through the Laravel AI SDK; the tests of the queue bind FakeDocumentReader instead
        $this->app->bind(DocumentReader::class, AiDocumentReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePolicies();

        // A password login lifts the limit of a linked phone
        Event::listen(Login::class, LiftLinkedDeviceLimit::class);

        // A worker lives for days: every job reads the settings of that moment, not those of the start
        Queue::before(fn () => resolve(InstallationSettings::class)->apply());

        // Only the proxies named in the configuration are believed about the address and https
        $proxies = config('app.trusted_proxies');

        if (is_array($proxies) && $proxies !== []) {
            TrustProxies::at($proxies);
        }
    }

    /**
     * All the data of the family has the same permissions (FamilyPolicy),
     * except documents, which a `member` uploads and edits too.
     */
    protected function configurePolicies(): void
    {
        // The family itself is not a row of a family, so the guess below does not find its policy
        Gate::policy(Family::class, FamilyPolicy::class);

        Gate::guessPolicyNamesUsing(fn (string $class): array => match (true) {
            // A model with rules of its own has an `App\Policies\<Model>Policy`; the others share FamilyPolicy.
            class_exists($own = 'App\\Policies\\'.class_basename($class).'Policy') && is_subclass_of($class, FamilyModel::class) => [$own],
            is_subclass_of($class, FamilyModel::class) => [FamilyPolicy::class],
            default => [],
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Polymorphic links store the type (`utility`), never the class name
        Relation::enforceMorphMap([...Linkables::TYPES, 'user' => User::class]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // At least 10 characters, as the forms say. No mandatory capitals or
        // symbols: the app is for parents, and a long sentence is already safe.
        // Online, passwords already exposed in a data breach are rejected too.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(10)->uncompromised()
            : Password::min(10),
        );
    }
}
