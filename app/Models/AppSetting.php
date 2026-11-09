<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One setting of the installation, stored in the database (see `InstallationSettings`). It belongs to no family.
 *
 * @property string $key
 * @property string|null $value
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|AppSetting newModelQuery()
 * @method static Builder<static>|AppSetting newQuery()
 * @method static Builder<static>|AppSetting query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['key', 'value', 'updated_at'])]
#[WithoutIncrementing]
#[WithoutTimestamps]
final class AppSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    /**
     * The attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['updated_at' => 'immutable_datetime'];
    }
}
