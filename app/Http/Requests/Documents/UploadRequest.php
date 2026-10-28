<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\Concerns\UploadsFiles;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The files chosen in *Upload*: at least one, with the rules of `UploadsFiles`.
 */
final class UploadRequest extends FormRequest
{
    use UploadsFiles;

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
        return $this->filesRules(required: true);
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
}
