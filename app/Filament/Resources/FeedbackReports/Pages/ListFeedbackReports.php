<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeedbackReports\Pages;

use App\Filament\Resources\FeedbackReports\FeedbackReportResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The list of the reports sent to the owner.
 */
final class ListFeedbackReports extends ListRecords
{
    protected static string $resource = FeedbackReportResource::class;
}
