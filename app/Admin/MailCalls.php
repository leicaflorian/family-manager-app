<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enums\CallOutcome;
use App\Models\MailInboxCall;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The query of the log of the calls of Mailgun (`mail_inbox_calls`), newest first, for the Posta page and for
 * `mail-inbox:calls`. The rows have no text of a mail in the columns that are shown; the payload is never selected.
 */
final class MailCalls
{
    /**
     * The calls that match the filters.
     *
     * @param  int|null  $familyId  only the calls of this family
     * @param  CallOutcome|null  $outcome  only the calls that ended like this
     * @param  CarbonImmutable|null  $since  only the calls from this moment on
     * @return Builder<MailInboxCall>
     */
    public static function query(?int $familyId = null, ?CallOutcome $outcome = null, ?CarbonImmutable $since = null): Builder
    {
        return MailInboxCall::query()
            ->select(['id', 'outcome', 'http_status', 'attachments', 'saved', 'family_id', 'created_at'])
            ->when($familyId !== null, fn (Builder $query) => $query->where('family_id', $familyId))
            ->when($outcome instanceof CallOutcome, fn (Builder $query) => $query->where('outcome', $outcome))
            ->when($since instanceof CarbonImmutable, fn (Builder $query) => $query->where('created_at', '>=', $since))
            ->latest()
            ->latest('id');
    }
}
