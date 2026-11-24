import { describe, expect, it } from 'vite-plus/test';
import { documentDetail } from '@/test/fixtures';
import {
    documentLine,
    documentSubtitle,
    documentValues,
    filterQuery,
    linkKey,
    linkQuery,
    newDocument,
    parseLinkKey,
    proposalOf,
    proposalValues,
    toPayload,
} from './documents';

describe('newDocument', () => {
    it('starts from the proposed title, active, with nothing else chosen', () => {
        expect(newDocument('Busta paga novembre 2026')).toEqual({
            title: 'Busta paga novembre 2026',
            category_id: '',
            person_id: '',
            organization_id: '',
            date: '',
            expiry_date: '',
            status: 'active',
            notes: '',
            tags: [],
            links: [],
        });
    });
});

describe('toPayload', () => {
    it('sends ids as numbers and what was left empty as null', () => {
        const values = {
            ...newDocument(' Busta paga '),
            category_id: '4',
            person_id: '7',
            date: '2026-11-27',
            tags: [' salary', '2026 ', '', 'salary'],
            links: [{ type: 'employment', id: 3 }],
        };

        expect(toPayload(values)).toEqual({
            title: 'Busta paga',
            category_id: 4,
            person_id: 7,
            organization_id: null,
            date: '2026-11-27',
            expiry_date: null,
            status: 'active',
            notes: null,
            tags: ['salary', '2026'],
            links: [{ type: 'employment', id: 3 }],
        });
    });
});

describe('linkKey', () => {
    it('writes a link as one value for a menu, and reads it back', () => {
        expect(linkKey({ type: 'employment', id: 3 })).toBe('employment:3');
        expect(parseLinkKey('employment:3')).toEqual({
            type: 'employment',
            id: 3,
        });
    });

    it('reads nothing from a value that is not a link', () => {
        expect(parseLinkKey('')).toBeNull();
        expect(parseLinkKey('employment')).toBeNull();
        expect(parseLinkKey('employment:x')).toBeNull();
    });
});

describe('linkQuery', () => {
    it('keeps the card on the address of Upload, or nothing without one', () => {
        expect(linkQuery({ type: 'vehicle', id: 1 })).toEqual({
            query: { link: 'vehicle:1' },
        });
        expect(linkQuery(undefined)).toEqual({});
    });
});

describe('documentValues', () => {
    it('fills the form with the document as it is', () => {
        expect(documentValues(documentDetail)).toEqual({
            title: 'Busta paga di ottobre',
            category_id: '4',
            person_id: '7',
            organization_id: '9',
            date: '2026-10-23',
            expiry_date: '',
            status: 'active',
            notes: 'With the arrears of the contract.',
            tags: ['salary', '2026'],
            links: [{ type: 'employment', id: 3 }],
        });
    });
});

describe('documentSubtitle', () => {
    it('joins category, person and organization, leaving out what is missing', () => {
        expect(
            documentSubtitle({
                category: 'Lavoro',
                person: 'Laura',
                organization: 'Comune di Bologna',
            }),
        ).toBe('Lavoro · Laura · Comune di Bologna');
        expect(
            documentSubtitle({
                category: 'Casa e utenze',
                person: null,
                organization: 'Edison',
            }),
        ).toBe('Casa e utenze · Edison');
    });
});

describe('filterQuery', () => {
    it('keeps only the filters in use', () => {
        expect(
            filterQuery({
                category: 4,
                person: null,
                organization: null,
                status: null,
                expiring: true,
                search: 'danza',
                card: null,
            }),
        ).toEqual({ category: 4, expiring: 1, search: 'danza' });
        expect(
            filterQuery({
                category: null,
                person: null,
                organization: null,
                status: null,
                expiring: false,
                search: null,
                card: 'vehicle:3',
            }),
        ).toEqual({ card: 'vehicle:3' });
    });
});

describe('proposalValues', () => {
    it('maps the proposal of the reading to the values of the form, leaving out what was not read', () => {
        expect(
            proposalValues({
                title: 'Luce ottobre',
                category_id: 4,
                person_id: null,
                organization_id: 9,
                date: '2026-10-15',
                expiry_date: null,
                links: [{ type: 'home', id: 2 }],
                headline: 'A light bill',
                found: [],
            }),
        ).toEqual({
            title: 'Luce ottobre',
            category_id: '4',
            organization_id: '9',
            date: '2026-10-15',
            links: [{ type: 'home', id: 2 }],
        });
    });
});

describe('proposalOf', () => {
    const proposal: App.Data.Reading.DocumentProposalData = {
        title: 'Luce ottobre',
        category_id: null,
        person_id: null,
        organization_id: null,
        date: null,
        expiry_date: null,
        links: [],
        headline: 'A light bill',
        found: [],
    };

    it('gives the values of the form once the reading is done', () => {
        expect(proposalOf('done', proposal)).toEqual({ title: 'Luce ottobre' });
    });

    it('gives nothing before the reading is done, or without a proposal', () => {
        // A file read again keeps its old proposal while it is read
        expect(proposalOf('reading', proposal)).toBeUndefined();
        expect(proposalOf('failed', proposal)).toBeUndefined();
        expect(proposalOf('done', null)).toBeUndefined();
        expect(proposalOf(null, undefined)).toBeUndefined();
    });
});

describe('documentLine', () => {
    const doc = {
        category: 'Anagrafica',
        person: null,
        organization: null,
        expiry_date: '2026-10-20',
    };

    it('without expiry is the subtitle of the document, normal', () => {
        expect(documentLine(doc, { today: '2026-10-27' })).toEqual({
            text: 'Anagrafica',
            tone: 'normal',
        });
    });

    it('withDate adds the date after the subtitle when there is no expiry to show', () => {
        const dated = { ...doc, date: '2024-02-02' };

        expect(
            documentLine(dated, { withDate: true, today: '2026-10-27' }),
        ).toEqual({ text: 'Anagrafica · 02/02/2024', tone: 'normal' });
        expect(
            documentLine(
                { ...dated, category: null, expiry_date: null },
                { expiry: true, withDate: true, today: '2026-10-27' },
            ),
        ).toEqual({ text: '02/02/2024', tone: 'normal' });
        expect(
            documentLine(dated, {
                expiry: true,
                withDate: true,
                today: '2026-10-27',
            }).text,
        ).toBe('Expired on 20/10/2026');
    });

    it('with expiry and no date to show falls back on the subtitle', () => {
        expect(
            documentLine(
                { ...doc, expiry_date: null },
                { expiry: true, today: '2026-10-27' },
            ),
        ).toEqual({ text: 'Anagrafica', tone: 'normal' });
    });

    it('with expiry, a passed date is overdue', () => {
        expect(
            documentLine(doc, { expiry: true, today: '2026-10-27' }),
        ).toEqual({ text: 'Expired on 20/10/2026', tone: 'overdue' });
    });

    it('with expiry, a date ahead is a warning unless told otherwise', () => {
        const ahead = { ...doc, expiry_date: '2026-12-11' };

        expect(
            documentLine(ahead, { expiry: true, today: '2026-10-27' }),
        ).toEqual({ text: 'Expires on 11/12/2026', tone: 'warning' });
        expect(
            documentLine(ahead, {
                expiry: true,
                today: '2026-10-27',
                aheadTone: 'normal',
            }),
        ).toEqual({ text: 'Expires on 11/12/2026', tone: 'normal' });
    });
});
