<?php

declare(strict_types=1);

namespace App\Cards;

/**
 * The kind of a field of a card, which decides the input of the form and the
 * validation rules.
 */
enum FieldKind: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Currency = 'currency';
    case Date = 'date';
    case Toggle = 'toggle';
    /** A choice among a few values (up to 4), shown as toggle buttons. */
    case Choice = 'choice';
    /** A choice among many values of an enum, shown as a menu. */
    case Dropdown = 'dropdown';
    case Person = 'person';
    case Organization = 'organization';
    /** Another card, of the type in `CardField::$target`. */
    case Card = 'card';
}
