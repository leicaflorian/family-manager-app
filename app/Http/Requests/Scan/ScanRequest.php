<?php

declare(strict_types=1);

namespace App\Http\Requests\Scan;

use App\Enums\FileSource;
use App\Http\Requests\Concerns\UploadsFiles;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The files of *Scan* sent by the phone or the scanner: a list with the rules of `UploadsFiles`, a note and the source.
 */
final class ScanRequest extends FormRequest
{
    use UploadsFiles;

    /**
     * Whoever adds documents uploads their scans.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    /**
     * The rules of the files, the note, the source and the name (the scanner's only).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->filesRules(required: true),
            'files' => ['required', 'array', 'list', 'max:20'],
            'note' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', Rule::in([FileSource::Phone->value, FileSource::Scanner->value])],
            'name' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * The messages that say what to do, instead of the generic ones.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->filesMessages();
    }

    /**
     * Where the files come from: the phone unless the scanner said so.
     */
    public function source(): FileSource
    {
        return FileSource::tryFrom($this->string('source')->toString()) ?? FileSource::Phone;
    }
}
