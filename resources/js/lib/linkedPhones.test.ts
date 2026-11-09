import { describe, expect, it } from 'vite-plus/test';
import { deviceSummary } from './linkedPhones';

const phone: App.Data.LinkedDeviceData = {
    id: 1,
    name: 'iPhone of Marco',
    linked_on: '2026-10-12',
    last_upload_on: null,
    linked_now: false,
};

/** Writes a date as the app does. */
const date = (value: string): string => value.split('-').reverse().join('/');

describe('deviceSummary', () => {
    it('says a phone that just entered is linked now', () => {
        expect(deviceSummary({ ...phone, linked_now: true }, date)).toBe(
            'Linked now',
        );
    });

    it('says when it was linked and that nothing was uploaded yet', () => {
        expect(deviceSummary(phone, date)).toBe(
            'Linked on 12/10/2026 · nothing uploaded yet',
        );
    });

    it('says when it was linked and when it last uploaded', () => {
        expect(
            deviceSummary({ ...phone, last_upload_on: '2026-10-24' }, date),
        ).toBe('Linked on 12/10/2026 · last upload on 24/10/2026');
    });
});
