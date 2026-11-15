<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackKind;
use App\Models\FeedbackReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The *Report a problem or an idea* panel: what it is about, the message, the page it was sent from, the layout of
 * the app and an optional screenshot or PDF up to 10 MB.
 */
final class StoreFeedbackRequest extends FormRequest
{
    /** The largest file, in kilobytes. */
    public const int MAX_FILE_KB = 10240;

    /** The layouts of the app the panel can be sent from. */
    public const array LAYOUTS = ['phone', 'tablet', 'computer'];

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
}
