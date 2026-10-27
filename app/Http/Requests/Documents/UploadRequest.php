<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The files chosen in *Upload*: PDF, JPG, PNG or HEIC, at most 25 MB each.
 * The type is the real one, read from the content, not from the name.
 */
final class UploadRequest extends FormRequest
{
    /**
     * Whoever adds documents uploads their files.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    /**
     * The rules of the files.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:25600'],
        ];
    }

    /**
     * The messages that say what to do, instead of the generic ones.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $tooBig = __('Each file can be at most 25 MB.');

        return [
            'files.required' => __('Choose at least one file.'),
            'files.max' => __('Upload at most 20 files at a time.'),
            'files.*.mimes' => __('Only PDF, JPG, PNG and HEIC files can be uploaded.'),
            'files.*.max' => $tooBig,
            'files.*.uploaded' => $tooBig,
        ];
    }
}
