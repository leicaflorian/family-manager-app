<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReadingPurpose;
use App\Models\Concerns\BelongsToFamily;
use Carbon\CarbonImmutable;
use Database\Factories\AiReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One AI call and what it cost (`ai_readings`), with the rules of a family model but no authors. A row is written once and never changed.
 *
 * @property int $id
 * @property int $family_id
 * @property int|null $user_id
 * @property int|null $file_id
 * @property ReadingPurpose $purpose
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property numeric|null $cost_usd
 * @property CarbonImmutable|null $created_at
 * @property-read Family $family
 * @property-read File|null $file
 * @property-read User|null $user
 *
 * @method static AiReadingFactory factory($count = null, $state = [])
 * @method static Builder<static>|AiReading newModelQuery()
 * @method static Builder<static>|AiReading newQuery()
 * @method static Builder<static>|AiReading query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['user_id', 'file_id', 'purpose', 'provider', 'model', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cost_usd'])]
final class AiReading extends Model
{
    // Not a FamilyModel: the table has no created_by/updated_by, so TracksAuthors would fail.
    use BelongsToFamily;

    /** @use HasFactory<AiReadingFactory> */
    use HasFactory;

    public const ?string UPDATED_AT = null;

    /**
     * Refuses every update: the rows are a record of what was spent.
     */
    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('An AI reading is never changed.');
        });
    }

    /**
     * The account that asked for the reading, if it still exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The file that was read, if it still exists.
     *
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => ReadingPurpose::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
