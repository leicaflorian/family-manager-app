<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new file of a document, chosen in *Edit*: the same types and size as in
 * *Upload*.
 */
final class ChangeFileRequest extends FormRequest
{
    /**
     * Whoever may change the document changes its file.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && ($this->user()?->can('update', $document) ?? false);
    }

    /**
     * The rule of the file.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['file' => UploadRequest::FILE];
    }

    /**
     * The messages that say what to do, as in *Upload*.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $tooBig = __('Each file can be at most 25 MB.');

        return [
            'file.required' => __('Choose a file.'),
            'file.mimes' => __('Only PDF, JPG, PNG and HEIC files can be uploaded.'),
            'file.max' => $tooBig,
            'file.uploaded' => $tooBig,
        ];
    }
}
