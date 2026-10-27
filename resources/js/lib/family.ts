/**
 * What the pages of *Family* write about people and organizations: the line
 * under a name, the icon of an organization, the filter of *Organizations*
 * and the values the forms start from.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    Briefcase,
    Building,
    Building2,
    GraduationCap,
    Hospital,
    Landmark,
    Shield,
    Store,
    Zap,
} from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';

/** The fields of the form of a person, as the inputs hold them. */
export type PersonFormValues = {
    first_name: string;
    last_name: string;
    initials: string;
    role: string;
    birth_date: string;
    tax_code: string;
    notes: string;
};

/** The fields of the form of an organization, as the inputs hold them. */
export type OrganizationFormValues = {
    name: string;
    types: string[];
    email: string;
    phone: string;
    website: string;
    notes: string;
};

/** A type in the filter of *Organizations*, with how many organizations have it. */
export type TypeFilter = { value: string; label: string; count: number };

/** The icon of each type of organization, as the canvas draws them. */
const TYPE_ICONS: Record<string, LucideIcon> = {
    bank: Landmark,
    insurance: Shield,
    utility_provider: Zap,
    employer: Briefcase,
    public_office: Building2,
    school: GraduationCap,
    healthcare: Hospital,
    shop: Store,
};

/**
 * The line under the name of a person: who they are and how old.
 *
 * @param person - the role and the age, if known
 * @returns for example «Parent · 44 years»
 */
export function personSubtitle(person: {
    role_label: string;
    age: number | null;
}): string {
    if (person.age === null) {
        return person.role_label;
    }

    const age = transChoice('{1} :count year|[2,*] :count years', person.age, {
        count: String(person.age),
    });

    return `${person.role_label} · ${age}`;
}

/**
 * Whether a person has an account, and with what level.
 *
 * @param person - the level of the account and whether it is whoever is looking
 * @returns for example «Administrator · you», or «No account»
 */
export function accountText(person: {
    account_label: string | null;
    is_me: boolean;
}): string {
    if (person.account_label === null) {
        return trans('No account');
    }

    return person.is_me
        ? `${person.account_label} · ${trans('you')}`
        : person.account_label;
}

/**
 * The icon of an organization, from its first type.
 *
 * @param types - the values of its types
 * @returns the Lucide icon; a building without a known type
 */
export function organizationIcon(types: readonly string[]): LucideIcon {
    return TYPE_ICONS[types[0] ?? ''] ?? Building;
}

/**
 * The line under the name of an organization: its types, then its records.
 *
 * @param organization - the labels of the types and the names of the records
 * @returns for example «Utility provider · Luce - Edison»
 */
export function organizationSubtitle(organization: {
    type_labels: readonly string[];
    records: readonly string[];
}): string {
    return [organization.type_labels.join(', '), ...organization.records]
        .filter((part) => part !== '')
        .join(' · ');
}

/**
 * The address of a website written without `https://`, as people write it.
 *
 * @param website - the website, as it was saved
 * @returns the address to open
 */
export function websiteHref(website: string): string {
    const address = website.trim();

    return /^https?:\/\//i.test(address) ? address : `https://${address}`;
}

/**
 * A text without capitals and accents, to compare names.
 *
 * @param text - the text
 * @returns the text in lower case, without the accents
 */
function plain(text: string): string {
    return text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
}

/**
 * The organizations of the chosen type whose name holds the search.
 *
 * @param organizations - all the organizations
 * @param type - the chosen type, or null for all
 * @param search - the text searched in the name, ignoring capitals and accents
 * @returns those that pass, in the same order
 */
export function filterOrganizations<
    T extends { name: string; types: readonly string[] },
>(organizations: readonly T[], type: string | null, search: string): T[] {
    const text = plain(search.trim());

    return organizations.filter(
        (organization) =>
            (type === null || organization.types.includes(type)) &&
            plain(organization.name).includes(text),
    );
}

/**
 * The types of the filter: those at least one organization has, with how many.
 *
 * @param organizations - all the organizations
 * @param types - every type, in the order the filter shows them
 * @returns the types with their count, without those nobody has
 */
export function typeFilters(
    organizations: readonly { types: readonly string[] }[],
    types: readonly App.Data.OptionData[],
): TypeFilter[] {
    return types
        .map((type) => ({
            value: type.value,
            label: type.label,
            count: organizations.filter((organization) =>
                organization.types.includes(type.value),
            ).length,
        }))
        .filter((type) => type.count > 0);
}

/**
 * The values the form of a person starts from.
 *
 * @param person - the person to change, or null for a new one
 * @returns the fields, with empty strings for what is missing
 */
export function personValues(
    person: Pick<
        App.Data.PersonDetailData,
        | 'first_name'
        | 'last_name'
        | 'initials'
        | 'role'
        | 'birth_date'
        | 'tax_code'
        | 'notes'
    > | null,
): PersonFormValues {
    return {
        first_name: person?.first_name ?? '',
        last_name: person?.last_name ?? '',
        initials: person?.initials ?? '',
        role: person?.role ?? 'parent',
        birth_date: person?.birth_date ?? '',
        tax_code: person?.tax_code ?? '',
        notes: person?.notes ?? '',
    };
}

/**
 * The values the form of an organization starts from.
 *
 * @param organization - the organization to change, or null for a new one
 * @returns the fields, with empty strings for what is missing
 */
export function organizationValues(
    organization: Pick<
        App.Data.OrganizationDetailData,
        'name' | 'types' | 'email' | 'phone' | 'website' | 'notes'
    > | null,
): OrganizationFormValues {
    return {
        name: organization?.name ?? '',
        types: [...(organization?.types ?? [])],
        email: organization?.email ?? '',
        phone: organization?.phone ?? '',
        website: organization?.website ?? '',
        notes: organization?.notes ?? '',
    };
}
