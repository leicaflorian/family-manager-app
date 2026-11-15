<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Enums\FeedbackKind;
use App\Mail\FeedbackReportMail;
use App\Models\FeedbackReport;
use App\Models\Owner;
use App\Models\ServerError;
use App\Models\User;
use App\Services\FileStore;
use App\Support\FeedbackDiagnostics;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Saves a report for the owner, keeps its file apart from the family's files and emails it to every owner account.
 */
final readonly class SubmitFeedback
{
    /**
     * Saves and sends the report.
     *
     * @param  User  $sender  who sends it, in the current family
     * @param  array{kind: FeedbackKind, message: string, page: string, layout: ?string, user_agent: ?string}  $data  the report
     * @param  UploadedFile|null  $file  the optional screenshot or PDF the person attached
     * @param  UploadedFile|null  $screenshot  the picture of the page the app took, for a problem
     * @param  array<mixed>|null  $diagnostics  the details of the browser as decoded from JSON, for a problem
     */
    public function handle(User $sender, array $data, ?UploadedFile $file, ?UploadedFile $screenshot = null, ?array $diagnostics = null): FeedbackReport
    {
        $report = FeedbackReport::query()->create([
            ...$data,
            'user_agent' => $data['user_agent'] !== null ? Str::limit($data['user_agent'], 509) : null,
            ...$this->storeFile($sender, $file),
            ...($data['kind'] === FeedbackKind::Problem ? $this->technicalPart($sender, $screenshot, $diagnostics) : []),
        ]);

        $owners = Owner::query()->pluck('email')->all();
        if ($owners !== []) {
            Mail::to($owners)->queue(new FeedbackReportMail($report));
        }

        return $report;
    }

    /**
     * The screenshot, the cleaned details and the account's server errors of a problem.
     *
     * @param  User  $sender  who sends it
     * @param  UploadedFile|null  $screenshot  the picture of the page, if any
     * @param  array<mixed>|null  $diagnostics  the details of the browser, if any
     * @return array{screenshot_path?: string, screenshot_size?: int|null, diagnostics: array<string, mixed>|null, server_errors: list<array<string, mixed>>|null}
     */
    private function technicalPart(User $sender, ?UploadedFile $screenshot, ?array $diagnostics): array
    {
        $details = $diagnostics !== null ? FeedbackDiagnostics::normalize($diagnostics, config()->string('app.version')) : null;

        // The ids of the requests the page saw fail also bring older errors of the same account.
        $requestIds = $details === null ? [] : array_values(array_filter(array_column($details['requests'], 'request_id')));

        // The technical part helps the owner but never costs the person their message: a failure is reported and skipped.
        try {
            $stored = $this->storeScreenshot($sender, $screenshot);
        } catch (Throwable $e) {
            report($e);
            $stored = [];
        }

        try {
            $serverErrors = array_values(ServerError::forReport($sender->id, now(), $requestIds)->map(fn (ServerError $error): array => $error->toReportArray())->all());
        } catch (Throwable $e) {
            report($e);
            $serverErrors = null;
        }

        return [...$stored, 'diagnostics' => $details, 'server_errors' => $serverErrors];
    }

    /**
     * The columns of the file the person attached.
     *
     * @param  User  $sender  who sends it
     * @param  UploadedFile|null  $file  the file, if any
     * @return array{file_path?: string, file_name?: string, file_mime?: string|null, file_size?: int|null}
     */
    private function storeFile(User $sender, ?UploadedFile $file): array
    {
        $stored = $this->storeUpload($sender, $file);

        return $stored === null ? [] : [
            'file_path' => $stored['path'],
            'file_name' => Str::limit($stored['name'], 250),
            'file_mime' => $stored['mime'],
            'file_size' => $stored['size'],
        ];
    }

    /**
     * The columns of the screenshot of the page.
     *
     * @param  User  $sender  who sends it
     * @param  UploadedFile|null  $screenshot  the picture, if any
     * @return array{screenshot_path?: string, screenshot_size?: int|null}
     */
    private function storeScreenshot(User $sender, ?UploadedFile $screenshot): array
    {
        $stored = $this->storeUpload($sender, $screenshot);

        return $stored === null ? [] : ['screenshot_path' => $stored['path'], 'screenshot_size' => $stored['size']];
    }

    /**
     * Stores an upload on the private disk, in the folder of the family's reports (the one place that does it).
     *
     * @param  User  $sender  who sends it
     * @param  UploadedFile|null  $upload  the upload, if any
     * @return array{path: string, name: string, mime: string|null, size: int|null}|null null when there is no upload
     */
    private function storeUpload(User $sender, ?UploadedFile $upload): ?array
    {
        if (! $upload instanceof UploadedFile) {
            return null;
        }

        $name = Str::uuid()->toString().'.'.($upload->guessExtension() ?? $upload->getClientOriginalExtension());

        return [
            'path' => (string) Storage::disk('local')->putFileAs(FileStore::feedbackFolder($sender->family_id), $upload, $name),
            'name' => $upload->getClientOriginalName(),
            'mime' => $upload->getMimeType(),
            'size' => $upload->getSize() ?: null,
        ];
    }
}
