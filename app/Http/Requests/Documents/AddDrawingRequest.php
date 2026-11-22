<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Rules\OfFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *Add a drawing* of the gallery: the file, the child, the title and the notes.
 */
final class AddDrawingRequest extends FormRequest
{
    /**
     * Whoever may add documents adds drawings.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,heic,heif,webp', 'max:25600'],
            'person_id' => ['nullable', 'integer', new OfFamily('people')],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The messages that say what to do.
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
