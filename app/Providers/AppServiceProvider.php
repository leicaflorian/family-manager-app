<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentFamily;
use App\Support\Schede;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una per richiesta (e per test): la famiglia impostata a mano non passa alla successiva
        $this->app->scoped(CurrentFamily::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Nei collegamenti polimorfici si scrive il tipo (`utility`), mai il nome della classe
        Relation::enforceMorphMap([...Schede::TIPI, 'user' => User::class]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Almeno 10 caratteri, come dicono i moduli. Niente maiuscole e simboli
        // obbligatori: l'app è per genitori, e una frase lunga è già sicura.
        // Online si scartano anche le password già finite in una fuga di dati.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(10)->uncompromised()
            : Password::min(10),
        );
    }
}
