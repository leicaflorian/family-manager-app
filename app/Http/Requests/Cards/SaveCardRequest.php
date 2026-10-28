<?php

declare(strict_types=1);

namespace App\Http\Requests\Cards;

use App\Cards\CardField;
use App\Cards\CardRules;
use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Cards\FieldKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The form of a card, new or changed (only an `admin`). The fields and their
 * rules come from the description of the card type in the route.
 */
final class SaveCardRequest extends FormRequest
{
    /**
     * The card type of the `type` of the route, for example `vehicle`.
     *
     * @throws NotFoundHttpException if the type is not a card
     */
    public function cardType(): CardType
    {
        $type = $this->route('type');

        return is_string($type) ? CardTypes::findOrFail($type) : throw new NotFoundHttpException;
    }

    /**
     * The card of the `id` of the route, or null on a new card.
     *
     * @throws ModelNotFoundException if the family has no such card (it answers 404)
     */
    public function card(): ?Model
    {
        $id = $this->route()?->parameters()['id'] ?? null;

        return is_string($id) || is_int($id) ? $this->cardType()->findOrFail($id) : null;
    }

    /**
     * A new card whoever adds them, a change whoever may change it.
     */
    public function authorize(): bool
    {
        $card = $this->card();

        return $card instanceof Model
            ? $this->user()?->can('update', $card) ?? false
            : $this->user()?->can('create', $this->cardType()->model()) ?? false;
    }

    /**
     * The rules of the fields.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return CardRules::for($this->cardType());
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $labels = [];

        foreach ($this->cardType()->fields() as $field) {
            $labels[$field->column] = mb_strtolower($field->label);
        }

        return $labels;
    }

    /**
     * An untouched switch arrives as null; a yes/no column is never null, so it
     * counts as off.
     */
    protected function prepareForValidation(): void
    {
        $off = [];

        foreach ($this->cardType()->fields() as $field) {
            if ($field->kind === FieldKind::Toggle && $this->has($field->column) && $this->input($field->column) === null) {
                $off[$field->column] = false;
            }
        }

        $this->merge($off);
    }

    /**
     * The validated columns of the card: without the virtual fields, with the
     * fields whose condition does not hold emptied (a policy that goes from car
     * to home loses its vehicle even if the form still sends it).
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $columns = [];

        foreach ($this->cardType()->fields() as $field) {
            if ($field->virtual) {
                continue;
            }

            if (! $this->isShown($field, $validated)) {
                $columns[$field->column] = null;
            } elseif (array_key_exists($field->column, $validated)) {
                $columns[$field->column] = $validated[$field->column];
            }
        }

        // A status left empty keeps the default of the column.
        if (array_key_exists('status', $columns) && $columns['status'] === null) {
            unset($columns['status']);
        }

        return $columns;
    }

    /**
     * Whether the condition of the field holds with the values sent.
     *
     * @param  CardField  $field  the field
     * @param  array<string, mixed>  $values  the validated values
     */
    private function isShown(CardField $field, array $values): bool
    {
        foreach ($field->showWhen as $column => $allowed) {
            $value = $values[$column] ?? null;

            if (! is_string($value) || ! in_array($value, $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
