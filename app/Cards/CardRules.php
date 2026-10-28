<?php

declare(strict_types=1);

namespace App\Cards;

use App\Rules\OfFamily;
use App\Support\Linkables;
use BackedEnum;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * The validation rules of a card type, derived from the description of its
 * fields, so a field is added in one place only.
 */
final class CardRules
{
    /**
     * The rules of every field of the card type, by column.
     *
     * @param  CardType  $type  the description of the card
     * @return array<string, list<mixed>>
     */
    public static function for(CardType $type): array
    {
        $rules = [];

        foreach ($type->fields() as $field) {
            $rules[$field->column] = [self::presence($field), ...self::ofKind($field)];
        }

        return $rules;
    }

    /**
     * Whether the field has to be there: always, only when its condition holds,
     * or never.
     *
     * @param  CardField  $field  the field
     */
    private static function presence(CardField $field): string
    {
        if (! $field->required) {
            return 'nullable';
        }

        if ($field->showWhen === []) {
            return 'required';
        }

        // The form can only ask one condition per field: the first column decides.
        $column = array_key_first($field->showWhen);

        return 'required_if:'.$column.','.implode(',', $field->showWhen[$column]);
    }

    /**
     * The rules of the kind of the field.
     *
     * @param  CardField  $field  the field
     * @return list<mixed>
     *
     * @throws LogicException if the field has no enum or target it needs
     */
    private static function ofKind(CardField $field): array
    {
        return match ($field->kind) {
            FieldKind::Text => ['string', 'max:255'],
            FieldKind::Textarea => ['string', 'max:5000'],
            FieldKind::Number => ['integer'],
            FieldKind::Currency => ['numeric', 'min:0'],
            FieldKind::Date => ['date_format:Y-m-d'],
            FieldKind::Toggle => ['boolean'],
            FieldKind::Choice, FieldKind::Dropdown => [self::choice($field)],
            FieldKind::Person => [new OfFamily('people')],
            FieldKind::Organization => [new OfFamily('organizations')],
            FieldKind::Card => [new OfFamily(self::table($field))],
        };
    }

    /**
     * The rule of a choice: the enum, or its values for a virtual field.
     *
     * @param  CardField  $field  the field
     *
     * @throws LogicException if the field has no enum
     */
    private static function choice(CardField $field): mixed
    {
        $enum = $field->enum ?? throw new LogicException('The field '.$field->column.' has no enum.');

        return $field->virtual
            ? Rule::in(array_map(static fn (BackedEnum $case): int|string => $case->value, $enum::cases()))
            : Rule::enum($enum);
    }

    /**
     * The table of the card a field links to.
     *
     * @param  CardField  $field  the field
     *
     * @throws LogicException if the field has no known target
     */
    private static function table(CardField $field): string
    {
        $model = Linkables::TYPES[$field->target ?? ''] ?? throw new LogicException('The field '.$field->column.' has no known target.');

        return new $model()->getTable();
    }
}
