<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\FeedbackReports;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gives the owner the screenshot (shown inline in the panel) and the attached file of a report. The route is
 * behind the owner guard; the report is found through the one query of the panel that lifts the family scope.
 */
final class FeedbackFileController
{
    /**
     * Streams a part of a report.
     *
     * @param  int  $report  the id of the report
     * @param  string  $part  `screenshot` (inline) or `file` (with the name it was sent with)
     *
     * @throws NotFoundHttpException when the report or the part is missing
     */
    public function __invoke(int $report, string $part): StreamedResponse
    {
        $found = FeedbackReports::query()->find($report);
        abort_if($found === null, 404);

        $path = $part === 'screenshot' ? $found->screenshot_path : $found->file_path;
        $disk = Storage::disk('local');
        abort_if($path === null || ! $disk->exists($path), 404);

        $headers = ['X-Content-Type-Options' => 'nosniff'];

        return $part === 'screenshot'
            ? $disk->response($path, basename($path), $headers, 'inline')
            : $disk->download($path, $found->file_name ?? basename($path), $headers);
    }
}
