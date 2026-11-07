<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Theme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The choice of the theme in *My account*: automatic, light or dark.
 */
final class UpdateThemeRequest extends FormRequest
{
    /**
     * The rules of the fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['theme' => ['required', Rule::enum(Theme::class)]];
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['theme' => __('theme')];
    }
}
