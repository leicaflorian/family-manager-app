<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Support\DocumentRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A document, new from a file of *Upload* or changed: its fields and the
 * records it links to. Every id must belong to the family.
 */
final class SaveDocumentRequest extends FormRequest
{
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
        $rules = DocumentRules::rules($this->input('links'));

        if (! $this->route('document') instanceof Document) {
            $rules['file_id'] = DocumentRules::fileId();
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
        return ['file_id' => __('file'), ...DocumentRules::attributes()];
    }

    /**
     * The validated columns of the document.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->safe()->only(DocumentRules::FIELDS);
    }

    /**
     * The validated links, ready for `DocumentLinks::sync()`.
     *
     * @return list<array{type: string, id: int}>
     */
    public function links(): array
    {
        return DocumentRules::links($this->safe()->array('links'));
    }
}
