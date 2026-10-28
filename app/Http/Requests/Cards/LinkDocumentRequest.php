<?php

declare(strict_types=1);

namespace App\Http\Requests\Cards;

use App\Cards\CardTypes;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The document to link to a card, chosen in *Link*. Whether the document
 * exists in the family and may be changed is checked by the controller, which
 * answers 404 for a document of another family.
 */
final class LinkDocumentRequest extends FormRequest
{
    /**
     * Looks the card up before validating, so a card of another family
     * answers 404 whatever the body is. The permission on the document is
     * checked by the controller.
     *
     * @throws NotFoundHttpException if the type is not a card
     * @throws ModelNotFoundException if the family has no such card
     */
    protected function prepareForValidation(): void
    {
        $type = $this->route('type');
        $id = $this->route()?->parameters()['id'] ?? null;

        if (! is_string($type) || ! is_string($id) && ! is_int($id)) {
            throw new NotFoundHttpException;
        }

        CardTypes::findOrFail($type)->findOrFail($id);
    }

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
