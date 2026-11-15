<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Decides the head of a page from its route: only *Log in* and *Sign up* can be found by search engines; every other
 * page, public with a secret in the address or of a family, is kept out of them.
 */
final class PageMeta
{
    /** The routes that search engines may list. */
    public const array INDEXABLE = ['login', 'register'];

    /** The title of the public pages, as the pages write it with `<Head>`. */
    private const array TITLES = [
        'login' => 'Log in',
        'register' => 'Sign up',
        'password.request' => 'Forgot password',
        'password.reset' => 'New password',
        'invitation.show' => 'Join the family',
        'connect' => 'Link the phone',
        'connect.show' => 'Link the phone',
        'connect.linked' => 'Phone linked',
    ];

    /** Width and height of the preview image, in px. */
    public const int IMAGE_WIDTH = 1200;

    public const int IMAGE_HEIGHT = 630;

    /**
     * The head of the page of the request, in the language already chosen by `SetLocale`.
     *
     * @param  Request  $request  the request, with or without a route (a 404 has none)
     * @return PageHead the title, description, robots rule and preview of the page
     */
    public function for(Request $request): PageHead
    {
        $name = $request->route()?->getName();
        $app = config()->string('app.name');
        $title = is_string($name) && isset(self::TITLES[$name]) ? __(self::TITLES[$name]).' - '.$app : $app;
        $indexable = self::indexable($request);

        return new PageHead(
            title: $title,
            description: __('The deadlines, the bills and the documents of the whole family, in one place.'),
            indexable: $indexable,
            canonical: $indexable ? $request->url() : null,
            image: url('/family-manager-og-1200x630.png'),
            locale: app()->getLocale() === 'it' ? 'it_IT' : 'en_US',
        );
    }

    /**
     * Whether search engines may list the page of the request.
     *
     * @param  Request  $request  the request, with or without a route
     * @return bool true only for *Log in* and *Sign up*
     */
    public static function indexable(Request $request): bool
    {
        return in_array($request->route()?->getName(), self::INDEXABLE, true);
    }
}
