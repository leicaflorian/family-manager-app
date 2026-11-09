<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OwnerActionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of the log of the owner (`owner_actions`): who did what to which family. It is not a family model:
 * the line outlives the family, which is why the name is copied.
 *
 * @property int $id
 * @property int|null $owner_id
 * @property int|null $family_id
 * @property string $family_name
 * @property int|null $user_id
 * @property OwnerActionType $action
 * @property CarbonImmutable|null $created_at
 *
 * @method static Builder<static>|OwnerAction newModelQuery()
 * @method static Builder<static>|OwnerAction newQuery()
 * @method static Builder<static>|OwnerAction query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['owner_id', 'family_id', 'family_name', 'user_id', 'action'])]
final class OwnerAction extends Model
{
    public const ?string UPDATED_AT = null;

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => OwnerActionType::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
