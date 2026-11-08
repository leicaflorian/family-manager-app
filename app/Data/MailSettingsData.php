<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\FamilySetting;
use App\Models\Person;
use App\Models\ReceivedMail;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything *Settings → Address for the bills* needs.
 */
#[TypeScript]
final class MailSettingsData extends Data
{
    /** How many senders the page lists. */
    public const int SENDERS = 8;

    /**
     * @param  string|null  $address  the address the bills are forwarded to; null while the mail is not active (no domain, or no address yet)
     * @param  list<MailSenderData>  $senders  who wrote already, the most recent first
     * @param  list<MailAccountData>  $accounts  the accounts of the family, with whether each gets the notice
     * @param  list<string>  $people_without_account  the first names of the people without an account, who get no notice
     * @param  bool  $attachments_only  whether a mail without files makes a notice in *To sort* but no bell
     * @param  bool  $can_edit  whether who looks can change the settings and the address (only an administrator)
     */
    public function __construct(
        public readonly ?string $address,
        public readonly array $senders,
        public readonly array $accounts,
        public readonly array $people_without_account,
        public readonly bool $attachments_only,
        public readonly bool $can_edit,
    ) {}

    /**
     * Builds it for the family of whoever looks.
     *
     * @param  FamilySetting  $settings  the settings of the family
     * @param  User  $user  whoever signed in
     */
    public static function fromSettings(FamilySetting $settings, User $user): self
    {
        $domain = config('services.mailgun.inbox_domain');
        $active = is_string($domain) && trim($domain) !== '' && $settings->mail_inbox_recipient !== null;
        /** @var list<int>|null $notify */
        $notify = $settings->mail_inbox_notify;

        return new self(
            address: $active ? $settings->mail_inbox_recipient : null,
            senders: self::senders(),
            accounts: array_values(User::query()->where('family_id', $user->family_id)->orderBy('id')->get()
                ->map(static fn (User $account): MailAccountData => MailAccountData::fromUser($account, $notify))
                ->all()),
            people_without_account: array_values(Person::query()->whereNull('user_id')->orderBy('first_name')->get()
                ->map(static fn (Person $person): string => $person->first_name)
                ->all()),
            attachments_only: $settings->mail_inbox_notify_attachments_only,
            can_edit: $user->can('update', $settings),
        );
    }

    /**
     * The senders that wrote most recently, each once (by address, and by name when an organization has several).
     *
     * @return list<MailSenderData>
     */
    private static function senders(): array
    {
        $latest = ReceivedMail::query()->selectRaw('max(id)')->groupBy('sender_address');

        $senders = ReceivedMail::query()->with('organization')->whereIn('id', $latest)
            ->latest('received_at')->orderByDesc('id')->get()
            ->map(static fn (ReceivedMail $mail): MailSenderData => new MailSenderData($mail->senderName(), $mail->sender_address))
            ->unique('name')->take(self::SENDERS);

        return array_values($senders->all());
    }
}
