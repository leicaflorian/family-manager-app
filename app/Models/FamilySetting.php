<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\FamilyScope;
use Carbon\CarbonImmutable;
use Database\Factories\FamilySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * The settings of a family: one row per family (`family_settings`).
 *
 * @property int $id
 * @property int $family_id
 * @property string|null $mail_inbox_recipient
 * @property array<array-key, mixed>|null $mail_inbox_notify
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property bool $mail_inbox_notify_attachments_only
 * @property-read Family $family
 *
 * @method static FamilySettingFactory factory($count = null, $state = [])
 * @method static Builder<static>|FamilySetting newModelQuery()
 * @method static Builder<static>|FamilySetting newQuery()
 * @method static Builder<static>|FamilySetting query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['mail_inbox_recipient', 'mail_inbox_notify', 'mail_inbox_notify_attachments_only'])]
final class FamilySetting extends FamilyModel
{
    /** @use HasFactory<FamilySettingFactory> */
    use HasFactory;

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mail_inbox_notify' => 'array',
            'mail_inbox_notify_attachments_only' => 'boolean',
        ];
    }

    /**
     * The settings that have this address for the bills. It looks outside the current family, like
     * `Invitation::findValid()`: the mail arrives when nobody is signed in, and the address says the family.
     *
     * @param  string  $address  the address the mail was sent to; case and surrounding spaces do not matter
     * @return self|null the settings, or null when it is not one address, or nobody has it
     */
    public static function findByMailRecipient(string $address): ?self
    {
        $address = strtolower(trim($address));

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return self::query()->withoutGlobalScope(FamilyScope::class)->where('mail_inbox_recipient', $address)->first();
    }

    /**
     * The accounts of the family that are told about the mail received: the ones in `mail_inbox_notify`, all of
     * them when it is null.
     *
     * @return Collection<int, User>
     */
    public function notifyUsers(): Collection
    {
        return User::query()
            ->where('family_id', $this->family_id)
            ->when($this->mail_inbox_notify !== null, fn ($query) => $query->whereIn('id', $this->mail_inbox_notify))
            ->orderBy('id')
            ->get();
    }
}
