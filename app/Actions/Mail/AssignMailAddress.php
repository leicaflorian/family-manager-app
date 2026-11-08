<?php

declare(strict_types=1);

namespace App\Actions\Mail;

use App\Models\Family;
use App\Models\FamilySetting;
use App\Support\CurrentFamily;
use App\Support\Mail\MailAddress;
use Closure;

/**
 * Gives a family the address its bills are forwarded to.
 */
final readonly class AssignMailAddress
{
    /** How many times a taken address is replaced by a new one before giving up. */
    private const int MAX_TRIES = 5;

    /**
     * @param  (Closure(string, string): string)|null  $maker  makes an address from the family name and the domain; the random one by default
     */
    public function __construct(private ?Closure $maker = null) {}

    /**
     * Writes a new address, in lower case, in the settings of the family (the row is created if missing). Does
     * nothing when `MAIL_INBOX_DOMAIN` is empty, and gives up (null) when five addresses in a row were taken.
     *
     * @param  Family  $family  the family
     * @return string|null the address written, or null when none was made
     */
    public function handle(Family $family): ?string
    {
        $domain = config('services.mailgun.inbox_domain');

        if (! is_string($domain) || trim($domain) === '') {
            return null;
        }

        $maker = $this->maker ?? MailAddress::make(...);

        for ($try = 0; $try < self::MAX_TRIES; $try++) {
            $address = strtolower($maker($family->name, trim($domain)));

            if (FamilySetting::query()->withoutGlobalScopes()->where('mail_inbox_recipient', $address)->exists()) {
                continue;
            }

            CurrentFamily::using($family->id, function () use ($address): void {
                FamilySetting::query()->firstOrCreate([])->forceFill(['mail_inbox_recipient' => $address])->save();
            });

            return $address;
        }

        return null;
    }
}
