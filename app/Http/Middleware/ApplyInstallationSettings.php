<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\InstallationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Copies the settings of the installation (database) into `config` before the request is handled.
 */
final readonly class ApplyInstallationSettings
{
    /**
     * @param  InstallationSettings  $settings  the settings of the installation
     */
    public function __construct(private InstallationSettings $settings) {}

    /**
     * Applies the settings and passes the request on.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->settings->apply();

        return $next($request);
    }
}
