<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackKind;
use App\Models\FeedbackReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The *Report a problem or an idea* panel: what it is about, the message, the page it was sent from, the layout of
 * the app and an optional screenshot or PDF up to 10 MB. For a problem it also reads the picture of the page and the
 * technical details the app adds, which never make the report fail: when they are not valid they are left out.
 */
final class StoreFeedbackRequest extends FormRequest
{
    /** The largest file, in kilobytes. */
    public const int MAX_FILE_KB = 10240;

    /** The layouts of the app the panel can be sent from. */
    public const array LAYOUTS = ['phone', 'tablet', 'computer'];

    /** The longest technical details read, in bytes; longer ones are left out without reading them. */
    public const int MAX_DIAGNOSTICS_BYTES = 262144;

    /**
     * Everyone of the family can send one.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', FeedbackReport::class) ?? false;
    }

    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(FeedbackKind::class)],
            'message' => ['required', 'string', 'max:5000'],
            'page' => ['required', 'string', 'max:2048'],
            'layout' => ['nullable', Rule::in(self::LAYOUTS)],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,heic,heif,pdf', 'max:'.self::MAX_FILE_KB],
        ];
    }

    /**
     * The picture of the page the app took, for a problem. The person cannot see or fix it, so a picture that is not
     * a valid image is left out instead of blocking the report.
     *
     * @return UploadedFile|null null for an idea, or when there is no valid picture
     */
    public function screenshot(): ?UploadedFile
    {
        $screenshot = $this->file('screenshot');
        if (! $this->isProblem() || ! $screenshot instanceof UploadedFile) {
            return null;
        }

        $valid = Validator::make(
            ['screenshot' => $screenshot],
            ['screenshot' => ['image', 'mimes:webp,jpeg,png', 'max:'.self::MAX_FILE_KB]],
        )->passes();

        return $valid ? $screenshot : null;
    }

    /**
     * The technical details of the browser, decoded from JSON, for a problem. Details that are too long, not JSON or
     * not an object are left out instead of blocking the report; the rest is cleaned by `FeedbackDiagnostics`.
     *
     * @return array<mixed>|null null for an idea, or when there are no usable details
     */
    public function diagnostics(): ?array
    {
        $details = $this->input('diagnostics');
        if (! $this->isProblem() || ! is_string($details) || strlen($details) > self::MAX_DIAGNOSTICS_BYTES) {
            return null;
        }

        $decoded = json_decode($details, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Whether the report is about a problem, the only kind that carries the technical part.
     */
    private function isProblem(): bool
    {
        return $this->input('kind') === FeedbackKind::Problem->value;
    }
}
