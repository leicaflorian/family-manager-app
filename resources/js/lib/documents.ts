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
    /** The tags, separated by commas, as the person writes them. */
    tags: string;
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
    tags: string[];
    notes: string | null;
    links: DocumentLink[];
};

/** The filters of *Documents*, as the server gives them back. */
export type DocumentFilters = {
    category: number | null;
    person: number | null;
    organization: number | null;
    status: string | null;
    expiring: boolean;
    search: string | null;
};

/** The choices of the filters of *Documents*. */
export type DocumentFilterOptions = {
    categories: App.Data.CategoryData[];
    people: App.Data.OptionData[];
    organizations: App.Data.OptionData[];
    statuses: App.Data.OptionData[];
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
        tags: '',
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
        tags: [
            ...new Set(
                values.tags
                    .split(',')
                    .map((tag) => tag.trim())
                    .filter((tag) => tag !== ''),
            ),
        ],
        notes: values.notes.trim() || null,
        links: values.links,
    };
}

/**
 * The form of an existing document, with its values.
 *
 * @param document - the document, as *Edit a document* receives it
 * @returns the values of the form
 */
export function documentValues(
    document: App.Data.DocumentDetailData,
): DocumentFormValues {
    return {
        title: document.title,
        category_id: fromId(document.category_id),
        person_id: fromId(document.person_id),
        organization_id: fromId(document.organization_id),
        date: document.date ?? '',
        expiry_date: document.expiry_date ?? '',
        status: document.status,
        tags: document.tags.join(', '),
        notes: document.notes ?? '',
        links: document.links.map(({ type, id }) => ({ type, id })),
    };
}

/**
 * The second line of a document in a list: category, person and organization.
 *
 * @param document - the parts of the line
 * @returns the parts there are, joined by a dot
 */
export function documentSubtitle(document: {
    category: string | null;
    person: string | null;
    organization: string | null;
}): string {
    return [document.category, document.person, document.organization]
        .filter((part): part is string => Boolean(part))
        .join(' · ');
}

/**
 * The filters in use, as the parameters of the address: those not set are
 * left out, so the address stays short.
 *
 * @param filters - the filters
 * @returns the parameters
 */
export function filterQuery(
    filters: DocumentFilters,
): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    for (const key of ['category', 'person', 'organization'] as const) {
        const value = filters[key];

        if (value !== null) {
            query[key] = value;
        }
    }

    if (filters.status) {
        query.status = filters.status;
    }

    if (filters.expiring) {
        query.expiring = 1;
    }

    if (filters.search) {
        query.search = filters.search;
    }

    return query;
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

/** The value of a menu for an id, empty when there is none. */
function fromId(id: number | null): string {
    return id === null ? '' : String(id);
}

/** The id chosen in a menu, or null when nothing is chosen. */
function toId(value: string): number | null {
    return value === '' ? null : Number(value);
}
