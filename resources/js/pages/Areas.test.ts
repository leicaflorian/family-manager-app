import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { allAreas, cardTypes } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Areas from './Areas.vue';

describe('Areas', () => {
    it('shows the nine areas with their cards and the overdue ones', () => {
        renderPage(Areas, {
            areas: allAreas,
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
            areas: allAreas,
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

    it('invites to create the first card, without starting from a document', () => {
        renderPage(Areas, {
            areas: allAreas.map((area) => ({
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
            screen.queryByText('Start from a document'),
        ).not.toBeInTheDocument();
    });

    it('lets only an admin create', () => {
        renderPage(Areas, {
            areas: allAreas,
            types: cardTypes,
            canCreate: false,
        });

        expect(
            screen.queryByRole('link', { name: 'New card' }),
        ).not.toBeInTheDocument();
    });
});
