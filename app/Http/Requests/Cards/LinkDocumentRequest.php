<?php

declare(strict_types=1);

namespace App\Http\Requests\Cards;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The document to link to a card, chosen in *Link*. Whether the document
 * exists in the family and may be changed is checked by the controller, which
 * answers 404 for a document of another family.
 */
final class LinkDocumentRequest extends FormRequest
{
    /**
     * The rule of the document.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['document_id' => ['required', 'integer']];
    }

    /**
     * The id of the document to link.
     */
    public function documentId(): int
    {
        return $this->integer('document_id');
    }
}
