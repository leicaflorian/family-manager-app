<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FamilySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property-read Family $family
 *
 * @method static FamilySettingFactory factory($count = null, $state = [])
 * @method static Builder<static>|FamilySetting newModelQuery()
 * @method static Builder<static>|FamilySetting newQuery()
 * @method static Builder<static>|FamilySetting query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['mail_inbox_recipient', 'mail_inbox_notify'])]
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
        ];
    }
}
