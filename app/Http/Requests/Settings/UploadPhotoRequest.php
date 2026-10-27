<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * *Change the photo*: an image up to 10 MB.
 */
final class UploadPhotoRequest extends FormRequest
{
    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240']];
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['photo' => __('photo')];
    }
}
