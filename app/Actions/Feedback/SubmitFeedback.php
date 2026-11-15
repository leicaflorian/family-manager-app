<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Enums\FeedbackKind;
use App\Mail\FeedbackReportMail;
use App\Models\FeedbackReport;
use App\Models\Owner;
use App\Models\User;
use App\Services\FileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * @param  UploadedFile|null  $file  the optional screenshot or PDF
     */
    public function handle(User $sender, array $data, ?UploadedFile $file): FeedbackReport
    {
        $report = FeedbackReport::query()->create([
            ...$data,
            'user_agent' => $data['user_agent'] !== null ? Str::limit($data['user_agent'], 509) : null,
            ...$this->storeFile($sender, $file),
        ]);

        $owners = Owner::query()->pluck('email')->all();
        if ($owners !== []) {
            Mail::to($owners)->queue(new FeedbackReportMail($report));
        }

        return $report;
    }

    /**
     * Stores the file on the private disk, in the folder of the family's reports.
     *
     * @param  User  $sender  who sends it
     * @param  UploadedFile|null  $file  the file, if any
     * @return array{file_path?: string, file_name?: string, file_mime?: string|null, file_size?: int|null}
     */
    private function storeFile(User $sender, ?UploadedFile $file): array
    {
        if (! $file instanceof UploadedFile) {
            return [];
        }

        $name = Str::uuid()->toString().'.'.($file->guessExtension() ?? $file->getClientOriginalExtension());

        return [
            'file_path' => (string) Storage::disk('local')->putFileAs(FileStore::feedbackFolder($sender->family_id), $file, $name),
            'file_name' => Str::limit($file->getClientOriginalName(), 250),
            'file_mime' => $file->getMimeType(),
            'file_size' => $file->getSize() ?: null,
        ];
    }
}
