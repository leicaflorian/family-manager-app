<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
     * No rules: the address is read leniently, and a value that is not valid (a tampered or old link) is ignored
     * instead of sending the person back with an error. An id of another family finds nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Which drawings to show: `all` or `favorites`.
     */
    public function filter(): string
    {
        return $this->query('filter') === 'favorites' ? 'favorites' : 'all';
    }

    /**
     * The child to show, if any.
     */
    public function person(): ?int
    {
        return $this->id('person');
    }

    /**
     * The drawing to open, if any.
     */
    public function drawing(): ?int
    {
        return $this->id('drawing');
    }

    /**
     * A positive whole number of the address, null when it is missing or is anything else.
     *
     * @param  string  $key  the name in the address
     */
    private function id(string $key): ?int
    {
        $value = $this->query($key);
        $id = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

        return $id === false ? null : $id;
    }
}
