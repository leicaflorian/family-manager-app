import { router } from '@inertiajs/vue3';
import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { policyDetail, policyFields } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import EditCard from './EditCard.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Edit a card', () => {
    const type: App.Data.CardTypeData = {
        type: 'insurance_policy',
        label: 'Insurance policy',
        description: '',
        area: 'insurance',
        area_label: 'Insurance',
    };

    it('names a new card and a change', () => {
        const first = renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: null,
        });
        expect(
            screen.getByRole('heading', { name: 'New insurance policy' }),
        ).toBeInTheDocument();
        first.unmount();

        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car' },
            card: policyDetail.card,
        });
        expect(
            screen.getByRole('heading', { name: 'Edit the insurance policy' }),
        ).toBeInTheDocument();
    });

    it('saves a change with PUT and a new card with POST', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const first = renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car', vehicle_id: '1' },
            card: policyDetail.card,
        });

        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            '/cards/insurance_policy/1',
            expect.objectContaining({ title: 'RC auto Panda' }),
            expect.anything(),
        );
        first.unmount();

        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: { title: 'Nuova', type: 'car' },
            card: null,
        });
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/cards/insurance_policy',
            expect.objectContaining({ title: 'Nuova' }),
            expect.anything(),
        );
    });

    it('cancels back to the card, or to the area for a new one', () => {
        const first = renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: policyDetail.card,
        });
        expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute(
            'href',
            '/cards/insurance_policy/1',
        );
        first.unmount();

        renderPage(EditCard, {
            type,
            fields: policyFields,
            values: {},
            card: null,
        });
        expect(screen.getByRole('link', { name: 'Cancel' })).toHaveAttribute(
            'href',
            '/areas/insurance',
        );
    });
});
