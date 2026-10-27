import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import {
    familySummary,
    organizationsList,
    organizationTypes,
} from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import Organizations from './Organizations.vue';

/** *Organizations* with three of them. */
function renderList(canEdit = true): void {
    renderPage(Organizations, {
        organizations: organizationsList,
        types: organizationTypes,
        family: familySummary,
        canEdit,
    });
}

describe('Organizations', () => {
    it('lists them with their types and their records', () => {
        renderList();

        expect(
            screen.getByRole('link', { name: 'Organizations 14' }),
        ).toHaveAttribute('aria-current', 'page');
        const edison = screen.getByRole('link', { name: /Edison/ });
        expect(edison).toHaveAttribute('href', '/family/organizations/1');
        expect(edison).toHaveTextContent('Utility provider · Luce - Edison');
        expect(
            screen.getByRole('link', { name: 'New organization' }),
        ).toHaveAttribute('href', '/family/organizations/create');
    });

    it('filters by type, with the types that have organizations', async () => {
        renderList();

        expect(screen.getByRole('button', { name: 'All 3' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(
            screen.queryByRole('button', { name: /Shop or service/ }),
        ).not.toBeInTheDocument();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Bank or lender 1' }),
        );

        expect(
            screen.getByRole('link', { name: /BPER Banca/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /Edison/ }),
        ).not.toBeInTheDocument();
    });

    it('searches by name, and says so when nothing matches', async () => {
        renderList();

        await fireEvent.update(
            screen.getByRole('searchbox', { name: 'Search an organization' }),
            'enel',
        );
        await settle();
        expect(
            screen.getByRole('link', { name: /Enel Energia/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /BPER/ }),
        ).not.toBeInTheDocument();

        await fireEvent.update(
            screen.getByRole('searchbox', { name: 'Search an organization' }),
            'coop',
        );
        expect(
            await screen.findByText('No organization matches.'),
        ).toBeInTheDocument();
    });

    it('without organizations invites to add the first, and a member cannot', () => {
        renderPage(Organizations, {
            organizations: [],
            types: organizationTypes,
            family: familySummary,
            canEdit: false,
        });

        expect(
            screen.getByText('No organizations, for now'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /New organization/ }),
        ).not.toBeInTheDocument();
    });
});
