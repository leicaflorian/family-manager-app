/**
 * What the pages of the cards know about each type and each field: the icon of a type, the input of a field. The
 * colours of the areas are in `areas.ts`.
 */
import type { LucideIcon } from '@lucide/vue';
import { create } from '@/routes/cards';
import {
    Briefcase,
    Car,
    ClipboardList,
    CreditCard,
    FileText,
    GraduationCap,
    HeartPulse,
    House,
    Landmark,
    Package,
    Repeat,
    Shield,
    Wrench,
    Zap,
} from '@lucide/vue';

/** The icon of each card type, as on the boards. */
const icons: Record<string, LucideIcon> = {
    home: House,
    utility: Zap,
    belonging: Package,
    maintenance_record: Wrench,
    vehicle: Car,
    insurance_policy: Shield,
    financial_account: CreditCard,
    loan: Landmark,
    subscription: Repeat,
    filing: ClipboardList,
    employment: Briefcase,
    health_event: HeartPulse,
    enrollment: GraduationCap,
};

/**
 * The icon of a card type.
 *
 * @param type - the type of the card, for example `vehicle`
 * @returns the Lucide component, or the sheet for an unknown type
 */
export function cardIcon(type: string): LucideIcon {
    return icons[type] ?? FileText;
}

/**
 * Where *New card* goes for an area: straight to the form when the area has a
 * single card type, else to the choice of the type among those of the area.
 *
 * @param types - the card types the person can create
 * @param area - the area, for example `vehicles`
 * @returns the address of the page
 */
export function newCardUrl(
    types: App.Data.CardTypeData[],
    area: string,
): string {
    const ofArea = types.filter((type) => type.area === area);
    const [only] = ofArea;

    return only !== undefined && ofArea.length === 1
        ? create({ query: { card: only.type } }).url
        : create({ query: { area } }).url;
}

/** The FormKit input of each kind of field of a card. */
const inputs: Record<string, string> = {
    text: 'text',
    textarea: 'textarea',
    number: 'number',
    currency: 'currency',
    date: 'datepicker',
    toggle: 'toggle',
    choice: 'togglebuttons',
    dropdown: 'autocomplete',
    person: 'autocomplete',
    organization: 'autocomplete',
    card: 'autocomplete',
};

/** The kinds whose input is a menu that can be left empty. */
const menus = new Set(['dropdown', 'person', 'organization', 'card']);

/**
 * The FormKit input that draws a field of a card.
 *
 * @param kind - the kind of the field (`CardFieldData.kind`)
 * @returns the type of the FormKit input, `text` for an unknown kind
 */
export function fieldInput(kind: string): string {
    return inputs[kind] ?? 'text';
}

/**
 * Whether a field of a card is a menu that can be left empty.
 *
 * @param kind - the kind of the field (`CardFieldData.kind`)
 * @returns true for a menu
 */
export function isMenuField(kind: string): boolean {
    return menus.has(kind);
}

/**
 * The text of the chosen value of a field, as the name of a card uses it: the
 * short `title` of its option (the first name of a person), else the label.
 *
 * @param field - the field, or undefined when the type has no such column
 * @param value - the value of the field now
 * @returns the text, or null when nothing is chosen
 */
function chosenText(
    field: App.Data.CardFieldData | undefined,
    value: unknown,
): string | null {
    // A value of a menu or of a text is text, an id from the server a number
    if (
        field === undefined ||
        (typeof value !== 'string' && typeof value !== 'number')
    ) {
        return null;
    }

    const text = String(value);

    if (text === '') {
        return null;
    }

    if (field.options.length === 0) {
        return text;
    }

    const option = field.options.find((choice) => choice.value === text);

    return option ? (option.title ?? option.label) : null;
}

/**
 * The name a card will have with the values now, as the server composes it
 * (`label()` of the card): for each part the first column with a value,
 * the parts joined with « - », the empty ones left out.
 *
 * @param parts - how the type composes its name (`titleParts`), empty for a type with its own *Name*
 * @param fields - the fields of the form, with their options
 * @param values - the values now, by column
 * @returns the name, empty while nothing composes it
 */
export function composeTitle(
    parts: string[][],
    fields: App.Data.CardFieldData[],
    values: Record<string, unknown>,
): string {
    const byColumn = new Map(fields.map((field) => [field.column, field]));

    return parts
        .map((columns) =>
            columns
                .map((column) =>
                    chosenText(byColumn.get(column), values[column]),
                )
                .find((text) => text !== null),
        )
        .filter((text): text is string => text !== undefined)
        .join(' - ');
}
