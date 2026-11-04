<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Support\DocumentRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A document changed: its fields and the
 * records it links to. Every id must belong to the family.
 */
final class SaveDocumentRequest extends FormRequest
{
    /**
     * A change whoever may change the document.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && ($this->user()?->can('update', $document) ?? false);
    }

    /**
     * The rules of the fields. New documents come from *Import* (`ImportRequest`), with the same rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return DocumentRules::rules($this->input('links'));
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return DocumentRules::attributes();
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
