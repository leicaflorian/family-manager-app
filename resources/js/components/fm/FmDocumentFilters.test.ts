import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { documentFilterOptions } from '@/test/fixtures';
import { choose, renderPage, settle } from '@/test/page';
import FmDocumentFilters from './FmDocumentFilters.vue';

const none = {
    category: null,
    person: null,
    organization: null,
    status: null,
    expiring: false,
    search: null,
};

describe('FmDocumentFilters', () => {
    it('sends the filters with the person chosen', async () => {
        const view = renderPage(FmDocumentFilters, {
            filters: none,
            options: documentFilterOptions,
        });

        await choose('Person', 'Laura Bianchi');

        expect(view.emitted('change')?.at(-1)).toEqual([
            { ...none, person: 7 },
        ]);
    });

    it('sends the expiring documents only', async () => {
        const view = renderPage(FmDocumentFilters, {
            filters: none,
            options: documentFilterOptions,
        });

        await fireEvent.click(
            screen.getByRole('switch', { name: 'Expiring within 60 days' }),
        );
        await settle();

        expect(view.emitted('change')?.at(-1)).toEqual([
            { ...none, expiring: true },
        ]);
    });
});
