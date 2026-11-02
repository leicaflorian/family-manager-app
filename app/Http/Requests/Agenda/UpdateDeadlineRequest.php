<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use App\Cards\DeadlineSource;
use App\Cards\DeadlineSources;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * *Update the date* and *Renewed* of a passed deadline in the Agenda: the new date of the card or the document. The
 * address (`field`, `id`) is resolved in `authorize()`, so an unknown kind of deadline or a card of another family
 * answers 404 before the body is validated; then whoever may edit that card may change it.
 */
final class UpdateDeadlineRequest extends FormRequest
{
    private ?Model $resolvedCard = null;

    /**
     * Whoever may edit the card or the document of the address.
     *
     * @throws NotFoundHttpException if the address is not a deadline of the family
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->card());
    }

    /**
     * The new date, and whether the deadline was renewed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'renewed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The kind of deadline of the address.
     *
     * @throws NotFoundHttpException if the kind is unknown
     */
    public function source(): DeadlineSource
    {
        $field = $this->route('field');

        return (is_string($field) ? DeadlineSources::find($field) : null) ?? throw new NotFoundHttpException;
    }

    /**
     * The card or the document of the address, in the current family.
     *
     * @throws NotFoundHttpException if there is none
     */
    public function card(): Model
    {
        $id = $this->route('id');

        return $this->resolvedCard ??= (is_string($id) && ctype_digit($id) ? $this->source()->model::query()->find((int) $id) : null)
            ?? throw new NotFoundHttpException;
    }
}
