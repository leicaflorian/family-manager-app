<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Mail\RegenerateMailAddress;
use App\Data\MailSettingsData;
use App\Http\Requests\UpdateMailSettingsRequest;
use App\Models\FamilySetting;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * *Settings → Address for the bills*: the address the family forwards its bills to, who already wrote, and who gets the
 * notice of a new file. Everyone sees it; only an administrator changes anything (checked by the policy).
 */
final class MailSettingsController extends Controller
{
    /**
     * The page.
     *
     * @param  User  $user  whoever signed in
     */
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/Mail', ['settings' => MailSettingsData::fromSettings($this->settings(), $user)]);
    }

    /**
     * Saves who gets the notice and whether it is only for mails with files.
     *
     * @param  UpdateMailSettingsRequest  $request  the choice, checked
     */
    public function update(UpdateMailSettingsRequest $request): RedirectResponse
    {
        $this->settings()->update([
            'mail_inbox_notify' => $request->notify(),
            'mail_inbox_notify_attachments_only' => $request->boolean('attachments_only'),
        ]);

        return to_route('mail-settings.edit');
    }

    /**
     * Changes the address: the old one stops receiving. Not while the mail is not active (no domain).
     *
     * @param  User  $user  whoever signed in
     * @param  RegenerateMailAddress  $regenerate  the action
     */
    public function address(#[CurrentUser] User $user, RegenerateMailAddress $regenerate): RedirectResponse
    {
        Gate::authorize('update', $this->settings());
        $domain = config('services.mailgun.inbox_domain');
        abort_unless(is_string($domain) && trim($domain) !== '', 404);

        $regenerate->handle($user->family);

        return to_route('mail-settings.edit');
    }

    /**
     * The settings of the family, created when an old family has none yet.
     */
    private function settings(): FamilySetting
    {
        return FamilySetting::query()->firstOrCreate([]);
    }
}
