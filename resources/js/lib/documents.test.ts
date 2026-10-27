import { describe, expect, it } from 'vite-plus/test';
import { linkKey, newDocument, parseLinkKey, toPayload } from './documents';

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
