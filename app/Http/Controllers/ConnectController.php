<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\PhoneLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Linking a phone with the code made on the computer. It is opened by a phone that has not entered yet, so
 * nothing here needs to be signed in; wrong codes are limited by network address.
 */
final class ConnectController extends Controller
{
    /**
     * @param  PhoneLinks  $links  the linking of the phones
     */
    public function __construct(private readonly PhoneLinks $links) {}

    /**
     * The page to write the code, for who could not scan the QR.
     */
    public function index(): Response
    {
        return Inertia::render('connect/Connect', ['code' => null, 'person' => null, 'family' => null]);
    }

    /**
     * The link of a code: checks it without using it (a message app that previews the link must not burn it) and
     * shows who it is for, with the button that uses it. A wrong code goes back to the page to write it.
     *
     * @param  Request  $request  the request
     * @param  string  $code  the code of the link
     */
    public function show(Request $request, string $code): Response|RedirectResponse
    {
        $this->abortIfBlocked($request);
        $entry = $this->links->find($code);

        if ($entry === null) {
            return $this->wrongCode($request, $code);
        }

        $user = User::query()->with('family')->find($entry['user_id']);

        if (! $user instanceof User) {
            return $this->wrongCode($request, $code);
        }

        return Inertia::render('connect/Connect', [
            'code' => PhoneLinks::format($code),
            'person' => $this->links->firstName($user),
            'family' => $user->family->name,
        ]);
    }

    /**
     * Uses the code: the phone is linked, enters with the account of the code and gets its cookie.
     *
     * @param  Request  $request  the request
     * @param  string  $code  the code
     */
    public function use(Request $request, string $code): RedirectResponse
    {
        $this->abortIfBlocked($request);
        $linked = $this->links->use($code, $request);

        if ($linked === null) {
            return $this->wrongCode($request, $code);
        }

        $this->links->signIn($linked['device'], $request);

        // The secret reaches the page once, which writes it after the `#`: it never goes through the address
        $request->session()->flash('phone_secret', $linked['secret']);

        return to_route('connect.linked')->withCookie($this->links->cookie($linked['secret'], $request));
    }

    /**
     * *Phone linked*: who the phone is for and how to put it on the Home screen. It is also the address that
     * the icon of the iPhone opens, with the secret after the `#`, so it answers without being signed in.
     *
     * @param  Request  $request  the request
     */
    public function linked(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('connect/Linked', [
            'name' => $user instanceof User ? $this->links->firstName($user) : null,
            'family' => $user instanceof User ? $user->family->name : null,
            'secret' => $user instanceof User ? $request->session()->pull('phone_secret') : null,
        ]);
    }

    /**
     * The icon of the iPhone does not share the cookies of Safari: it sends the secret it kept in its address.
     * A phone with that secret enters and gets the cookie again.
     *
     * @param  Request  $request  the request
     */
    public function resume(Request $request): RedirectResponse
    {
        $this->abortIfBlocked($request);
        $secret = $request->input('secret');
        $device = is_string($secret) && $secret !== '' ? LinkedDevice::findByToken($secret) : null;

        if (! $device instanceof LinkedDevice) {
            RateLimiter::hit($this->key($request), PhoneLinks::CODE_MINUTES * 60);

            return to_route('connect.linked')->withErrors(['secret' => __('This phone is not linked any more: link it again with a new code')]);
        }

        $this->links->signIn($device, $request);

        return to_route('scan')->withCookie($this->links->cookie((string) $secret, $request));
    }

    /**
     * Counts a wrong code and goes back to the page to write it, with what is wrong.
     *
     * @param  Request  $request  the request
     * @param  string  $code  what was written
     */
    private function wrongCode(Request $request, string $code): RedirectResponse
    {
        RateLimiter::hit($this->key($request), PhoneLinks::CODE_MINUTES * 60);

        // A code that cannot be one is a typo; a well-formed one that is not found is most likely out of time
        $message = PhoneLinks::normalize($code) === null
            ? __('This code is not right: check that you wrote it well')
            : __('The code has expired: make a new one on the computer');

        return to_route('connect')->withErrors(['code' => $message]);
    }

    /**
     * Stops whoever got too many codes wrong.
     *
     * @param  Request  $request  the request
     */
    private function abortIfBlocked(Request $request): void
    {
        abort_if(RateLimiter::tooManyAttempts($this->key($request), PhoneLinks::MAX_WRONG), 429, __('Too many attempts: try again in a few minutes'));
    }

    /**
     * The counter of the wrong codes of a network address.
     *
     * @param  Request  $request  the request
     */
    private function key(Request $request): string
    {
        return 'phone-link:wrong:'.$request->ip();
    }
}
