<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The address of the gallery: `?filter=favorites&person=3&drawing=12`.
 */
final class GalleryRequest extends FormRequest
{
    /**
     * Everyone in the family sees the gallery.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Document::class) ?? false;
    }

    /**
     * The rules of the filters; an id of another family finds nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::in(['all', 'favorites'])],
            'person' => ['nullable', 'integer'],
            'drawing' => ['nullable', 'integer'],
        ];
    }

    /**
     * Which drawings to show: `all` or `favorites`.
     */
    public function filter(): string
    {
        return $this->string('filter')->toString() === 'favorites' ? 'favorites' : 'all';
    }

    /**
     * The child to show, if any.
     */
    public function person(): ?int
    {
        return $this->filled('person') ? $this->integer('person') : null;
    }

    /**
     * The drawing to open, if any.
     */
    public function drawing(): ?int
    {
        return $this->filled('drawing') ? $this->integer('drawing') : null;
    }
}
