<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Rules\OfFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The files chosen in *To sort* to be read: at least one, all of the family.
 */
final class ReadToSortRequest extends FormRequest
{
    /**
     * Whoever adds documents has the files read.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    /**
     * The ids of the files.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [new OfFamily('files')],
        ];
    }

    /**
     * The ids chosen.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), array_filter($this->array('ids'), is_numeric(...))));
    }
}
