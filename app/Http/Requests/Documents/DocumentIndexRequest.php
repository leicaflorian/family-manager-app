<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Cards\CardTypes;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\PresetLink;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The filters of *Documents*, in the address: `?category=4&search=danza`.
 */
final class DocumentIndexRequest extends FormRequest
{
    /**
     * Everyone in the family sees the documents.
     */
    public function authorize(): bool
    {
        if (! ($this->user()?->can('viewAny', Document::class) ?? false)) {
            return false;
        }

        // A card that is not one of the family (or does not exist) is not found, never an error that tells it exists
        abort_if($this->filled('card') && $this->card() === null, 404);

        return true;
    }

    /**
     * The rules of the filters. An id of another family finds nothing: the
     * list is already only of the family.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'integer'],
            'person' => ['nullable', 'integer'],
            'organization' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'expiring' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'document' => ['nullable', 'integer'],
            'card' => ['nullable', 'string'],
        ];
    }

    /**
     * The filters with their types, for `FilterDocuments::apply()` and for the page.
     *
     * @return array{category: int|null, person: int|null, organization: int|null, status: string|null, expiring: bool, search: string|null, record: array{type: string, id: int}|null}
     */
    public function filters(): array
    {
        return [
            'category' => $this->filled('category') ? $this->integer('category') : null,
            'person' => $this->filled('person') ? $this->integer('person') : null,
            'organization' => $this->filled('organization') ? $this->integer('organization') : null,
            'status' => $this->filled('status') ? $this->string('status')->toString() : null,
            'expiring' => $this->boolean('expiring'),
            'search' => $this->filled('search') ? $this->string('search')->trim()->toString() : null,
            'record' => $this->card(),
        ];
    }

    /**
     * The card the list is limited to (`?card=vehicle:3`), null without one or when it is not of the family.
     *
     * @return array{type: string, id: int}|null
     */
    public function card(): ?array
    {
        return PresetLink::from($this->input('card'));
    }

    /**
     * The name of the card the list is limited to, for the chip of the page.
     */
    public function cardLabel(): ?string
    {
        $card = $this->card();

        if ($card === null) {
            return null;
        }

        $model = CardTypes::findOrFail($card['type'])->findOrFail($card['id']);

        return method_exists($model, 'label') ? (string) $model->label() : null;
    }
}
