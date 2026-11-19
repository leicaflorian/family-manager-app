<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Route;

/**
 * Links to the app made while serving another host: the owner panel lives on its own host
 * (`ADMIN_DOMAIN`), and a link it writes for a family must point to the host of the app.
 */
final class AppHostUrl
{
    /**
     * A new URL generator on the host of the app ({@see config('app.url')}), so the generator of the request is left
     * as it was. Not a clone of it: a clone shares the route generator, which keeps writing with the original root.
     */
    public static function generator(): UrlGenerator
    {
        $root = rtrim(config()->string('app.url'), '/');
        if (! str_contains($root, '://')) {
            $root = 'https://'.$root;
        }

        $generator = new UrlGenerator(Route::getRoutes(), Request::create($root));
        $generator->forceRootUrl($root);
        // The key that `signed` checks against, as the generator of the application has it
        $generator->setKeyResolver(static fn (): string => config()->string('app.key'));

        return $generator;
    }

    /**
     * The address of a file of `public/` on the host of the app, for the pictures of a mail: a mail sent from the
     * panel would otherwise point them to the host of the panel.
     *
     * @param  string  $path  the file, for example `mail-icons/welcome.png`
     */
    public static function asset(string $path): string
    {
        $generator = self::generator();
        // Files served from elsewhere (`ASSET_URL`), as the generator of the application has them
        $origin = config('app.asset_url');
        $generator->useAssetOrigin(is_string($origin) ? $origin : null);

        return $generator->asset($path);
    }
}
