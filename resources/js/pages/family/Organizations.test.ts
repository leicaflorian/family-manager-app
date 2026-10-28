import { fireEvent, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import {
    familySummary,
    organizationsList,
    organizationTypes,
} from '@/test/fixtures';
import { layOut, watchLayout } from '@/test/layout';
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

    it('puts New organization in the menu when there is no room for it', async () => {
        watchLayout();
        renderList();

        await layOut(40);

        expect(
            screen.queryByRole('link', { name: 'New organization' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        expect(
            await screen.findByRole('menuitem', { name: 'New organization' }),
        ).toHaveAttribute('href', '/family/organizations/create');
    });

    it('shows the logo of whoever has one, the icon of the type for the others', () => {
        renderPage(Organizations, {
            organizations: organizationsList.map((organization) =>
                organization.id === 1
                    ? { ...organization, logo: '/files/41' }
                    : organization,
            ),
            types: organizationTypes,
            family: familySummary,
            canEdit: true,
        });

        expect(screen.getByRole('img', { name: 'Edison' })).toHaveAttribute(
            'src',
            '/files/41',
        );
        expect(screen.queryByRole('img', { name: 'Enel Energia' })).toBeNull();
    });
});
