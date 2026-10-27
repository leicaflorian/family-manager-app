/**
 * The values of the document form, the same in *Upload* and in *Edit a
 * document*, and what the form sends.
 */

/** A record the document links to: its type, as the morph map writes it, and its id. */
export type DocumentLink = { type: string; id: number };

/** The fields of the form: the menus hold strings, empty when nothing is chosen. */
export type DocumentFormValues = {
    title: string;
    category_id: string;
    person_id: string;
    organization_id: string;
    date: string;
    expiry_date: string;
    status: string;
    notes: string;
    links: DocumentLink[];
};

/** What the form sends: ids as numbers, and null for what was left empty. */
export type DocumentPayload = {
    title: string;
    category_id: number | null;
    person_id: number | null;
    organization_id: number | null;
    date: string | null;
    expiry_date: string | null;
    status: string;
    notes: string | null;
    links: DocumentLink[];
};

/** The choices of the form, as the server gives them. */
export type DocumentFormOptions = {
    categories: App.Data.CategoryData[];
    people: App.Data.OptionData[];
    organizations: App.Data.OptionData[];
    links: App.Data.LinkGroupData[];
    statuses: App.Data.OptionData[];
};

/**
 * The form of a new document, from the title proposed for its file.
 *
 * @param title - the proposed title
 * @returns the values of the form
 */
export function newDocument(title: string): DocumentFormValues {
    return {
        title,
        category_id: '',
        person_id: '',
        organization_id: '',
        date: '',
        expiry_date: '',
        status: 'active',
        notes: '',
        links: [],
    };
}

/**
 * What the form sends from its values.
 *
 * @param values - the values of the form
 * @returns the data for the server
 */
export function toPayload(values: DocumentFormValues): DocumentPayload {
    return {
        title: values.title.trim(),
        category_id: toId(values.category_id),
        person_id: toId(values.person_id),
        organization_id: toId(values.organization_id),
        date: values.date || null,
        expiry_date: values.expiry_date || null,
        status: values.status,
        notes: values.notes.trim() || null,
        links: values.links,
    };
}

/**
 * A link as one value of a menu, `employment:3`.
 *
 * @param link - the link
 * @returns the value
 */
export function linkKey(link: DocumentLink): string {
    return `${link.type}:${link.id}`;
}

/**
 * The link written by `linkKey()`.
 *
 * @param key - the value of the menu
 * @returns the link, or null if the value is not one
 */
export function parseLinkKey(key: string): DocumentLink | null {
    const [type, id] = key.split(':');

    return type && id && /^\d+$/.test(id) ? { type, id: Number(id) } : null;
}

/** The id chosen in a menu, or null when nothing is chosen. */
function toId(value: string): number | null {
    return value === '' ? null : Number(value);
}
