<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Rules\OfFamily;
use App\Support\Linkables;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A document, new from a file of *Upload* or changed: its fields and the
 * records it links to. Every id must belong to the family.
 */
final class SaveDocumentRequest extends FormRequest
{
    /** The columns of the document the form fills. */
    private const array FIELDS = ['title', 'category_id', 'person_id', 'organization_id', 'date', 'expiry_date', 'status', 'tags', 'notes'];

    /**
     * A new document whoever adds documents, a change whoever may change it.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document
            ? $this->user()?->can('update', $document) ?? false
            : $this->user()?->can('create', Document::class) ?? false;
    }

    /**
     * The rules of the fields. A new document also needs its file, one of
     * *Upload* still waiting for its document.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', new OfFamily('document_categories')],
            'person_id' => ['nullable', 'integer', new OfFamily('people')],
            'organization_id' => ['nullable', 'integer', new OfFamily('organizations')],
            'date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(DocumentStatus::class)],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'links' => ['nullable', 'array', 'max:50'],
            'links.*' => ['required', 'array:type,id'],
            'links.*.type' => ['required', 'string', Rule::in(array_keys(Linkables::WITH_DOCUMENTS))],
            ...$this->linkIdRules(),
        ];

        if (! $this->route('document') instanceof Document) {
            $rules['file_id'] = ['required', 'integer', new OfFamily('files'), Rule::exists('files', 'id')->where('to_sort', true)];
        }

        return $rules;
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file_id' => __('file'),
            'title' => __('title'),
            'category_id' => __('category'),
            'person_id' => __('person'),
            'organization_id' => __('organization'),
            'date' => __('date'),
            'expiry_date' => __('expiry date'),
            'status' => __('status'),
            'tags.*' => __('tag'),
            'links.*.type' => __('link'),
            'links.*.id' => __('link'),
        ];
    }

    /**
     * The validated columns of the document.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->safe()->only(self::FIELDS);
    }

    /**
     * The validated links, ready for `DocumentLinks::sync()`.
     *
     * @return list<array{type: string, id: int}>
     */
    public function links(): array
    {
        $links = [];

        foreach ($this->safe()->array('links') as $link) {
            if (is_array($link) && is_string($link['type'] ?? null) && is_numeric($link['id'] ?? null)) {
                $links[] = ['type' => $link['type'], 'id' => (int) $link['id']];
            }
        }

        return $links;
    }

    /**
     * The rule of each link's id: a record of the family, in the table of its
     * type. A link with a wrong type gets only `required`: its type is the error.
     *
     * @return array<string, list<ValidationRule|string>>
     */
    private function linkIdRules(): array
    {
        $rules = [];

        foreach ($this->array('links') as $index => $link) {
            $type = is_array($link) ? $link['type'] ?? null : null;
            $rules["links.{$index}.id"] = is_string($type) && isset(Linkables::WITH_DOCUMENTS[$type])
                ? ['required', 'integer', new OfFamily((new (Linkables::TYPES[$type]))->getTable())]
                : ['required'];
        }

        return $rules;
    }
}
