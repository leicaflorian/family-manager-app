<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeedbackReports\Pages;

use App\Filament\Resources\FeedbackReports\FeedbackReportResource;
use App\Models\FeedbackReport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The page of a report, with the download of its file.
 */
final class ViewFeedbackReport extends ViewRecord
{
    protected static string $resource = FeedbackReportResource::class;

    /**
     * The report of the page.
     */
    public function report(): FeedbackReport
    {
        $report = $this->getRecord();
        assert($report instanceof FeedbackReport);

        return $report;
    }

    /**
     * *Download the file*, only for a report with a file.
     *
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label(__('Download the file'))
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->report()->file_path !== null)
                ->action(fn (): StreamedResponse => Storage::disk('local')->download(
                    (string) $this->report()->file_path,
                    $this->report()->file_name,
                )),
        ];
    }
}
