<?php

declare(strict_types=1);

namespace App\Admin;

use App\Models\FeedbackReport;
use App\Models\Scopes\FamilyScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * The reports sent to the owner from *Report a problem or an idea*, for the owner panel, newest first. The one
 * place where the panel reads what a family wrote: the person sent it to the owner on purpose. The panel has no
 * current family, so the family scope is lifted here and nowhere in `app/Filament/`.
 */
final class FeedbackReports
{
    /**
     * The reports of every family, with the family and who sent them.
     *
     * @return Builder<FeedbackReport>
     */
    public static function query(): Builder
    {
        return FeedbackReport::query()
            ->withoutGlobalScope(FamilyScope::class)
            ->with(['family:id,name', 'creator:id,name,email'])
            ->latest()
            ->latest('id');
    }
}
