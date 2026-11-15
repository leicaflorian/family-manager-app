<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackKind;
use Carbon\CarbonImmutable;
use Database\Factories\FeedbackReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report sent to the owner from *Report a problem or an idea* (`feedback_reports`): what it is about, the message,
 * the page and the device it came from, and an optional file.
 *
 * @property int $id
 * @property int $family_id
 * @property FeedbackKind $kind
 * @property string $message
 * @property string $page The path of the page the person was on
 * @property string|null $user_agent
 * @property string|null $layout phone, tablet or computer
 * @property string|null $file_path On the local disk, under feedback/
 * @property string|null $file_name
 * @property string|null $file_mime
 * @property int|null $file_size Bytes
 * @property string|null $screenshot_path On the local disk, under feedback/
 * @property int|null $screenshot_size Bytes
 * @property array<string, mixed>|null $diagnostics Device, console errors, failed requests, last pages
 * @property list<array<string, mixed>>|null $server_errors The account's server errors around the sending
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Family $family
 * @property-read User|null $creator
 *
 * @method static FeedbackReportFactory factory($count = null, $state = [])
 * @method static Builder<static>|FeedbackReport newModelQuery()
 * @method static Builder<static>|FeedbackReport newQuery()
 * @method static Builder<static>|FeedbackReport query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['kind', 'message', 'page', 'user_agent', 'layout', 'file_path', 'file_name', 'file_mime', 'file_size', 'screenshot_path', 'screenshot_size', 'diagnostics', 'server_errors'])]
final class FeedbackReport extends FamilyModel
{
    /** @use HasFactory<FeedbackReportFactory> */
    use HasFactory;

    /**
     * The account that sent the report (`created_by`), null once the account is gone.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The casts of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackKind::class,
            'file_size' => 'integer',
            'screenshot_size' => 'integer',
            'diagnostics' => 'array',
            'server_errors' => 'array',
        ];
    }
}
