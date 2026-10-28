import { describe, expect, it } from 'vite-plus/test';
import { documentDetail } from '@/test/fixtures';
import {
    documentSubtitle,
    documentValues,
    filterQuery,
    linkKey,
    newDocument,
    parseLinkKey,
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
            }),
        ).toEqual({});
    });
});
