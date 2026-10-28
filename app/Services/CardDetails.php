<?php

declare(strict_types=1);

namespace App\Services;

use App\Cards\CardField;
use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Cards\FieldKind;
use App\Data\CardAddData;
use App\Data\CardData;
use App\Data\CardDateData;
use App\Data\CardDetailData;
use App\Data\CardFieldData;
use App\Data\CardTypeData;
use App\Data\CardValueData;
use App\Data\OptionData;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Queries\DocumentRows;
use App\Support\Linkables;
use App\Support\ModelId;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Everything the page of one card needs: its detail, as the person reads it,
 * and the data of its form, both built from the description of the card type.
 */
final readonly class CardDetails
{
    public function __construct(private CardSummaries $summaries, private DocumentRows $documents) {}

    /**
     * The detail of a card.
     *
     * @param  Model  $card  a record of one of the 13 models
     * @param  User  $user  whoever looks at the card
     *
     * @throws InvalidArgumentException if the record is not a card
     */
    public function of(Model $card, User $user): CardDetailData
    {
        $type = CardTypes::for($card);
        $row = $this->summaries->row($card);

        return new CardDetailData(
            card: $row,
            heading: $this->heading($type, $card, $row),
            dates: $this->dates($type, $card),
            notices: $type->notices($card),
            values: $this->values($type, $card),
            related: $this->related($type, $card),
            addable: CardTypes::canCreate($user) ? $this->addable($type, $card) : [],
            documents: $this->documents->of(['record' => ['type' => $type->type(), 'id' => $row->id]]),
            can_edit: Gate::forUser($user)->allows('update', $card),
            can_delete: Gate::forUser($user)->allows('delete', $card),
            can_delete_documents: Gate::forUser($user)->allows('deleteAny', Document::class),
        );
    }

    /**
     * The data of the form of a card type.
     *
     * @param  CardType  $type  the description of the card type
     * @param  Model|null  $card  the card to edit, or null for a new one
     * @param  array<string, mixed>  $preset  values from the address (`?vehicle_id=3`); the ones that are not fields are ignored
     * @return array{type: CardTypeData, fields: list<CardFieldData>, values: array<string, mixed>}
     */
    public function form(CardType $type, ?Model $card, array $preset): array
    {
        $fields = $type->fields();
        $values = $card instanceof Model ? $this->formValues($fields, $card) : $this->presets($fields, $preset);

        return [
            'type' => CardTypeData::fromCardType($type),
            'fields' => array_map($this->fieldData(...), $fields),
            'values' => $values,
        ];
    }

    /**
     * The value of a field as the person reads it.
     *
     * @param  CardField  $field  the field
     * @param  mixed  $value  the value saved in the column
     * @return string|null null when the field is empty
     */
    public function text(CardField $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field->kind) {
            FieldKind::Text, FieldKind::Textarea, FieldKind::Number => is_scalar($value) ? (string) $value : null,
            FieldKind::Currency => is_numeric($value) ? (Number::currency((float) $value, 'EUR', 'it') ?: null) : null,
            FieldKind::Date => $this->day($value)?->format('d/m/Y'),
            FieldKind::Toggle => $this->translated((bool) $value ? 'Yes' : 'No'),
            FieldKind::Choice, FieldKind::Dropdown => $value instanceof BackedEnum && method_exists($value, 'label') ? (string) $value->label() : null,
            FieldKind::Person => is_int($value) ? $this->personName($value) : null,
            FieldKind::Organization => is_int($value) ? Organization::query()->find($value)?->name : null,
            FieldKind::Card => is_int($value) ? $this->cardLabel($field, $value) : null,
        };
    }

    /**
     * The line under the name: the kind of card, the piece of data that
     * recognises it and who it belongs to.
     */
    private function heading(CardType $type, Model $card, CardData $row): string
    {
        $kind = $type->label();
        $owner = null;

        foreach ($type->fields() as $field) {
            $value = $card->getAttribute($field->column);

            if ($field->column === 'type' && $value instanceof BackedEnum && method_exists($value, 'label')) {
                $kind = (string) $value->label();
            }
            if ($owner === null && $field->kind === FieldKind::Person && is_int($value)) {
                $owner = mb_strtolower($field->label).' '.$this->person($value)?->first_name;
            }
        }

        return implode(' · ', array_filter([$kind, $row->subtitle, $owner], static fn (?string $part): bool => $part !== null && trim($part) !== ''));
    }

    /**
     * The dates of the tiles that are filled in.
     *
     * @return list<CardDateData>
     */
    private function dates(CardType $type, Model $card): array
    {
        $dates = [];

        foreach ($type->dates() as $date) {
            $day = $this->day($card->getAttribute($date->column));
            if ($day instanceof CarbonImmutable) {
                $dates[] = new CardDateData($date->label, $day->format('Y-m-d'), $date->expires);
            }
        }

        return $dates;
    }

    /**
     * The lines of data, without the empty fields and the virtual ones.
     *
     * @return list<CardValueData>
     */
    private function values(CardType $type, Model $card): array
    {
        $values = [];

        foreach ($type->fields() as $field) {
            if ($field->virtual) {
                continue;
            }
            $value = $card->getAttribute($field->column);
            $text = $this->text($field, $value);

            if ($text !== null) {
                $values[] = new CardValueData($field->labelFor($card), $text, is_int($value) ? $this->href($field, $value) : null);
            }
        }

        return $values;
    }

    /**
     * The address of the person or of the organization a field names.
     */
    private function href(CardField $field, int $id): ?string
    {
        return match ($field->kind) {
            FieldKind::Person => route('people.show', $id),
            FieldKind::Organization => route('organizations.show', $id),
            default => null,
        };
    }

    /**
     * The cards linked to this one, in both directions, as rows.
     *
     * @return list<CardData>
     */
    private function related(CardType $type, Model $card): array
    {
        $rows = [];

        foreach ($type->related() as $relation) {
            $found = $card->$relation;
            $cards = $found instanceof Collection ? $found->all() : [$found];

            foreach ($cards as $other) {
                if ($other instanceof Model) {
                    $rows[] = $this->summaries->row($other);
                }
            }
        }

        return $rows;
    }

    /**
     * The cards that can be added already linked to this one, each with the
     * address of its form.
     *
     * @return list<CardAddData>
     *
     * @throws InvalidArgumentException if the card has no numeric id
     */
    private function addable(CardType $type, Model $card): array
    {
        return array_map(
            static fn (array $entry): CardAddData => new CardAddData(
                type: $entry['type']->type(),
                label: $entry['type']->label(),
                href: route('cards.create', ['card' => $entry['type']->type(), ...$entry['preset']], false),
            ),
            CardTypes::addableTo($type, ModelId::of($card)),
        );
    }

    /**
     * The value of every field of a saved card, as the form takes it.
     *
     * @param  list<CardField>  $fields
     * @return array<string, mixed>
     */
    private function formValues(array $fields, Model $card): array
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $field->virtual ? $this->virtualValue($field, $card) : $card->getAttribute($field->column);

            // The ids of a menu are text, like the values of its options (FormKit compares them strictly).
            $values[$field->column] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof CarbonInterface => $value->format('Y-m-d'),
                is_scalar($value) && $this->isReference($field) => (string) $value,
                default => $value,
            };
        }

        return $values;
    }

    /**
     * Whether the field holds the id of a person, an organization or another card.
     */
    private function isReference(CardField $field): bool
    {
        return in_array($field->kind, [FieldKind::Person, FieldKind::Organization, FieldKind::Card], true);
    }

    /**
     * The value of a virtual field: the first column of the fields it decides that is filled in
     * (a maintenance refers to a home, a belonging or a vehicle).
     */
    private function virtualValue(CardField $field, Model $card): ?string
    {
        foreach ($card->getAttributes() as $column => $value) {
            if ($value !== null && Str::endsWith($column, '_id') && $this->isShownBy($field, Str::beforeLast($column, '_id'))) {
                return Str::beforeLast($column, '_id');
            }
        }

        return null;
    }

    /**
     * Whether the choices of the virtual field include the value.
     */
    private function isShownBy(CardField $field, string $value): bool
    {
        $enum = $field->enum ?? throw new LogicException('The field '.$field->column.' has no enum.');

        return in_array($value, array_map(static fn (BackedEnum $case): int|string => $case->value, $enum::cases()), true);
    }

    /**
     * The presets of a new card: only the fields of the form, in the type of the field.
     *
     * @param  list<CardField>  $fields
     * @param  array<string, mixed>  $preset
     * @return array<string, mixed>
     */
    private function presets(array $fields, array $preset): array
    {
        $values = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field->column, $preset)) {
                continue;
            }
            $value = $preset[$field->column];

            $values[$field->column] = match ($field->kind) {
                FieldKind::Person, FieldKind::Organization, FieldKind::Card => is_numeric($value) ? (string) (int) $value : null,
                FieldKind::Number => is_numeric($value) ? (int) $value : null,
                FieldKind::Currency => is_numeric($value) ? (float) $value : null,
                FieldKind::Toggle => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => is_scalar($value) ? (string) $value : null,
            };
        }

        return $values;
    }

    /**
     * A field of the form with its choices.
     */
    private function fieldData(CardField $field): CardFieldData
    {
        return new CardFieldData(
            column: $field->column,
            label: $field->label,
            kind: $field->kind->value,
            required: $field->required,
            options: $this->options($field),
            show_when: $field->showWhen,
            label_when: $field->labelWhen,
        );
    }

    /**
     * The choices of a field: the values of an enum, the people, the
     * organizations or the cards of the target type.
     *
     * @return list<OptionData>
     */
    private function options(CardField $field): array
    {
        return match ($field->kind) {
            FieldKind::Choice, FieldKind::Dropdown => OptionData::list($this->enumOptions($field)),
            FieldKind::Person => array_values(Person::query()->orderBy('first_name')->orderBy('last_name')->get()
                ->map(static fn (Person $person): OptionData => new OptionData((string) $person->id, "{$person->first_name} {$person->last_name}"))->all()),
            FieldKind::Organization => array_values(Organization::query()->orderBy('name')->get()
                ->map(static fn (Organization $organization): OptionData => new OptionData((string) $organization->id, $organization->name))->all()),
            FieldKind::Card => $this->cardOptions($field),
            default => [],
        };
    }

    /**
     * The options of the enum of a field, with their labels.
     *
     * @return list<array{value: string, label: string}>
     *
     * @throws LogicException if the field has no enum with labels
     */
    private function enumOptions(CardField $field): array
    {
        $enum = $field->enum ?? throw new LogicException('The field '.$field->column.' has no enum.');

        return method_exists($enum, 'options') ? $enum::options() : throw new LogicException($enum.' has no labels.');
    }

    /**
     * The cards of the target type of a field, by name.
     *
     * @return list<OptionData>
     */
    private function cardOptions(CardField $field): array
    {
        $class = Linkables::TYPES[$field->target ?? ''] ?? throw new LogicException('The field '.$field->column.' has no known target.');
        $options = [];

        foreach ($class::query()->with($class::labelRelations())->get() as $other) {
            $key = $other->getKey();
            if (is_int($key)) {
                $options[] = new OptionData((string) $key, $this->labelOf($other));
            }
        }
        usort($options, static fn (OptionData $a, OptionData $b): int => strnatcasecmp($a->label, $b->label));

        return $options;
    }

    /**
     * The name of the card a field links to.
     */
    private function cardLabel(CardField $field, int $id): ?string
    {
        $class = Linkables::TYPES[$field->target ?? ''] ?? null;

        return ($other = $class === null ? null : $class::query()->find($id)) instanceof Model ? $this->labelOf($other) : null;
    }

    /**
     * The name of a card, from its model.
     *
     * @throws LogicException if the model has no `label()`
     */
    private function labelOf(Model $card): string
    {
        return method_exists($card, 'label') ? (string) $card->label() : throw new LogicException($card::class.' has no label().');
    }

    /**
     * A translated text, always a string.
     *
     * @param  string  $key  the English text
     */
    private function translated(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }

    /**
     * The full name of a person of the family.
     */
    private function personName(int $id): ?string
    {
        $person = $this->person($id);

        return $person instanceof Person ? "{$person->first_name} {$person->last_name}" : null;
    }

    /**
     * A person of the family.
     */
    private function person(int $id): ?Person
    {
        return Person::query()->find($id);
    }

    /**
     * A date column as a day.
     */
    private function day(mixed $value): ?CarbonImmutable
    {
        return match (true) {
            $value instanceof CarbonInterface => CarbonImmutable::parse($value->format('Y-m-d'), 'Europe/Rome')->startOfDay(),
            is_string($value) && $value !== '' => CarbonImmutable::parse($value, 'Europe/Rome')->startOfDay(),
            default => null,
        };
    }
}
