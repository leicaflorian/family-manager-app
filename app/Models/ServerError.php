<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ServerErrorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One server error, kept as a short row for 30 days (`server_errors`). It is not a family model: the account is empty
 * when the error came before anyone was signed in, and the rows are read only to copy them into a report.
 *
 * @property int $id
 * @property string|null $request_id
 * @property int|null $user_id
 * @property int|null $family_id
 * @property string|null $method
 * @property string|null $path
 * @property string $class
 * @property string $message
 * @property string|null $file
 * @property int|null $line
 * @property CarbonImmutable|null $created_at
 *
 * @method static ServerErrorFactory factory($count = null, $state = [])
 * @method static Builder<static>|ServerError newModelQuery()
 * @method static Builder<static>|ServerError newQuery()
 * @method static Builder<static>|ServerError query()
 *
 * @mixin \Eloquent
 */
#[Fillable(['request_id', 'user_id', 'family_id', 'method', 'path', 'class', 'message', 'file', 'line'])]
final class ServerError extends Model
{
    /** @use HasFactory<ServerErrorFactory> */
    use HasFactory;

    use MassPrunable;

    /** How many days an error stays. */
    public const int KEPT_DAYS = 30;

    /** How many minutes before a report its account's errors are taken. */
    public const int REPORT_WINDOW_MINUTES = 30;

    public const ?string UPDATED_AT = null;

    /**
     * The errors of an account for its report: those of the 30 minutes up to the sending, plus older ones of the same
     * account whose request id the page saw fail. A request id of another account is never taken.
     *
     * @param  list<string>  $requestIds  the ids of the failed requests the page knows
     * @return Collection<int, ServerError> oldest first
     */
    public static function forReport(int $userId, CarbonInterface $sentAt, array $requestIds): Collection
    {
        return self::query()
            ->where('user_id', $userId)
            ->where(function (Builder $query) use ($sentAt, $requestIds): void {
                $query->whereBetween('created_at', [$sentAt->copy()->subMinutes(self::REPORT_WINDOW_MINUTES), $sentAt])
                    ->orWhereIn('request_id', $requestIds);
            })->oldest()
            ->orderBy('id')
            ->get();
    }

    /**
     * The errors older than 30 days.
     *
     * @return Builder<ServerError>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(self::KEPT_DAYS));
    }

    /**
     * The row as the report keeps it.
     *
     * @return array{at: string, request_id: ?string, method: ?string, path: ?string, class: string, message: string, file: ?string, line: ?int}
     */
    public function toReportArray(): array
    {
        return [
            'at' => $this->created_at?->toIso8601String() ?? '',
            'request_id' => $this->request_id,
            'method' => $this->method,
            'path' => $this->path,
            'class' => $this->class,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }

    /**
     * The types of the columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'family_id' => 'integer',
            'line' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
