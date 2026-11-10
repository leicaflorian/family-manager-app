import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { allAreas, cardTypes } from '@/test/fixtures';
import { renderPage, screenWidth } from '@/test/page';
import Areas from './Areas.vue';

// The shared props of the page (the tools beside the title read the notices)
vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({ url: '/', props: { unread_notices: 2 } }),
}));

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('Areas', () => {
    it('has the magnifier and the bell beside the title on the phone only', () => {
        const props = { groups: allAreas, types: cardTypes, canCreate: true };
        screenWidth(390);
        const phone = renderPage(Areas, props);

        expect(
            screen.getByRole('link', { name: 'Search in Family Manager' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Notices/ }),
        ).toBeInTheDocument();
        phone.unmount();

        screenWidth(1280);
        renderPage(Areas, props);

        expect(
            screen.queryByRole('link', { name: 'Search in Family Manager' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Notices/ }),
        ).not.toBeInTheDocument();
    });

    it('shows the areas with their cards and the overdue ones', () => {
        renderPage(Areas, {
            groups: allAreas,
            types: cardTypes,
            canCreate: true,
        });

        expect(
            screen.getByRole('link', { name: /^Home.*3 cards.*1 overdue$/ }),
        ).toHaveAttribute('href', '/areas/home');
        expect(screen.getByText('3 cards')).toBeInTheDocument();
        expect(screen.getByText('1 overdue')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'New card' }),
        ).toBeInTheDocument();
    });

    it('finds a card in every area by its name', async () => {
        renderPage(Areas, {
            groups: allAreas,
            types: cardTypes,
            canCreate: true,
        });

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search all cards' }),
            'edison',
        );

        expect(
            screen.getByRole('link', { name: /Luce - Edison/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /Fiat Panda/ }),
        ).not.toBeInTheDocument();
    });

    it('shows only the areas the family did not hide', () => {
        renderPage(Areas, {
            groups: allAreas.map((area) =>
                area.area === 'vehicles' ? { ...area, hidden: true } : area,
            ),
            types: cardTypes,
            canCreate: true,
        });

        expect(screen.getByRole('link', { name: /^Home/ })).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /^Vehicles/ }),
        ).not.toBeInTheDocument();
    });

    it('search finds a card of a hidden area', async () => {
        renderPage(Areas, {
            groups: allAreas.map((area) =>
                area.area === 'vehicles' ? { ...area, hidden: true } : area,
            ),
            types: cardTypes,
            canCreate: true,
        });

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search all cards' }),
            'panda',
        );

        expect(
            screen.getByRole('link', { name: /Fiat Panda/ }),
        ).toBeInTheDocument();
    });

    it('a family whose cards are all in hidden areas does not see the empty state', () => {
        renderPage(Areas, {
            groups: allAreas.map((area) => ({
                ...area,
                hidden: area.count > 0,
            })),
            types: cardTypes,
            canCreate: true,
        });

        expect(
            screen.queryByRole('link', { name: 'Create the first card' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('searchbox', { name: 'Search all cards' }),
        ).toBeInTheDocument();
    });

    it('invites to create the first card, or to start from a document', () => {
        renderPage(Areas, {
            groups: allAreas.map((area) => ({
                ...area,
                count: 0,
                overdue: 0,
                cards: [],
            })),
            types: cardTypes,
            canCreate: true,
        });

        expect(
            screen.getByRole('link', { name: 'Create the first card' }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: 'Add' })).toHaveLength(9);
        expect(
            screen.getByRole('link', { name: 'Start from a document' }),
        ).toHaveAttribute('href', '/upload');
    });

    it('lets only an admin create', () => {
        renderPage(Areas, {
            groups: allAreas,
            types: cardTypes,
            canCreate: false,
        });

        expect(
            screen.queryByRole('link', { name: 'New card' }),
        ).not.toBeInTheDocument();
    });
});
