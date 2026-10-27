<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Document;
use App\Models\FamilyModel;
use App\Models\User;
use App\Policies\DocumentPolicy;
use App\Policies\FamilyPolicy;
use App\Support\CurrentFamily;
use App\Support\Linkables;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePolicies();
    }

    /**
     * All the data of the family has the same permissions (FamilyPolicy),
     * except documents, which a `member` uploads and edits too.
     */
    protected function configurePolicies(): void
    {
        Gate::guessPolicyNamesUsing(fn (string $class): array => match (true) {
            $class === Document::class => [DocumentPolicy::class],
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
