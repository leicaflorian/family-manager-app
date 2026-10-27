import { Building, Landmark, Zap } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import {
    accountText,
    filterOrganizations,
    organizationIcon,
    organizationSubtitle,
    organizationValues,
    personSubtitle,
    personValues,
    typeFilters,
    websiteHref,
} from './family';

const edison = {
    id: 1,
    name: 'Edison',
    types: ['utility_provider'],
    type_labels: ['Utility provider'],
    records: ['Luce - Edison'],
};
const bper = {
    id: 2,
    name: 'BPER Banca',
    types: ['bank'],
    type_labels: ['Bank or lender'],
    records: ['Conto BPER', 'Mutuo casa'],
};
const neri = {
    id: 3,
    name: 'Dott.ssa Nèri',
    types: [],
    type_labels: [],
    records: [],
};

describe('personSubtitle', () => {
    it('says who they are and how old', () => {
        expect(personSubtitle({ role_label: 'Parent', age: 44 })).toBe(
            'Parent · 44 years',
        );
        expect(personSubtitle({ role_label: 'Child', age: 1 })).toBe(
            'Child · 1 year',
        );
    });

    it('leaves the age out when the birth date is missing', () => {
        expect(personSubtitle({ role_label: 'Other', age: null })).toBe(
            'Other',
        );
    });
});

describe('accountText', () => {
    it('gives the level, and «you» for whoever is looking', () => {
        expect(
            accountText({ account_label: 'Administrator', is_me: true }),
        ).toBe('Administrator · you');
        expect(
            accountText({ account_label: 'Family member', is_me: false }),
        ).toBe('Family member');
    });

    it('says when there is no account', () => {
        expect(accountText({ account_label: null, is_me: false })).toBe(
            'No account',
        );
    });
});

describe('organizationIcon', () => {
    it('takes the icon of the first type', () => {
        expect(organizationIcon(['utility_provider', 'shop'])).toBe(Zap);
        expect(organizationIcon(['bank'])).toBe(Landmark);
    });

    it('is a building without a known type', () => {
        expect(organizationIcon([])).toBe(Building);
        expect(organizationIcon(['spaceship'])).toBe(Building);
    });
});

describe('organizationSubtitle', () => {
    it('puts the types, then the records', () => {
        expect(organizationSubtitle(edison)).toBe(
            'Utility provider · Luce - Edison',
        );
        expect(organizationSubtitle(bper)).toBe(
            'Bank or lender · Conto BPER · Mutuo casa',
        );
        expect(organizationSubtitle(neri)).toBe('');
    });
});

describe('websiteHref', () => {
    it('adds https to an address written without it', () => {
        expect(websiteHref('edison.it')).toBe('https://edison.it');
        expect(websiteHref('http://edison.it/area-clienti')).toBe(
            'http://edison.it/area-clienti',
        );
        expect(websiteHref(' HTTPS://edison.it ')).toBe('HTTPS://edison.it');
    });
});

describe('filterOrganizations', () => {
    const all = [bper, neri, edison];

    it('keeps everyone without a type or a search', () => {
        expect(filterOrganizations(all, null, '')).toEqual(all);
    });

    it('keeps those of the chosen type', () => {
        expect(filterOrganizations(all, 'bank', '')).toEqual([bper]);
    });

    it('searches the name ignoring capitals and accents', () => {
        expect(filterOrganizations(all, null, ' neri')).toEqual([neri]);
        expect(filterOrganizations(all, null, 'EDI')).toEqual([edison]);
        expect(filterOrganizations(all, 'bank', 'edi')).toEqual([]);
    });
});

describe('typeFilters', () => {
    it('lists the types that have organizations, in the order given, with how many', () => {
        const types = [
            { value: 'bank', label: 'Bank or lender', help: null },
            { value: 'insurance', label: 'Insurance', help: null },
            {
                value: 'utility_provider',
                label: 'Utility provider',
                help: null,
            },
        ];

        expect(typeFilters([edison, bper, neri, edison], types)).toEqual([
            { value: 'bank', label: 'Bank or lender', count: 1 },
            { value: 'utility_provider', label: 'Utility provider', count: 2 },
        ]);
    });
});

describe('personValues', () => {
    it('starts empty, as a parent, for a new person', () => {
        expect(personValues(null)).toEqual({
            first_name: '',
            last_name: '',
            initials: '',
            role: 'parent',
            birth_date: '',
            tax_code: '',
            notes: '',
        });
    });

    it('takes the values of the person, with empty strings for what is missing', () => {
        expect(
            personValues({
                first_name: 'Giulia',
                last_name: 'Bianchi',
                initials: 'GB',
                role: 'child',
                birth_date: '2016-05-24',
                tax_code: null,
                notes: null,
            }),
        ).toEqual({
            first_name: 'Giulia',
            last_name: 'Bianchi',
            initials: 'GB',
            role: 'child',
            birth_date: '2016-05-24',
            tax_code: '',
            notes: '',
        });
    });
});

describe('organizationValues', () => {
    it('starts empty for a new organization', () => {
        expect(organizationValues(null)).toEqual({
            name: '',
            types: [],
            email: '',
            phone: '',
            website: '',
            notes: '',
        });
    });

    it('takes the values of the organization', () => {
        expect(
            organizationValues({
                name: 'Edison',
                types: ['utility_provider'],
                email: null,
                phone: '800 123 456',
                website: 'edison.it',
                notes: 'Codice cliente 40291837',
            }),
        ).toEqual({
            name: 'Edison',
            types: ['utility_provider'],
            email: '',
            phone: '800 123 456',
            website: 'edison.it',
            notes: 'Codice cliente 40291837',
        });
    });
});
