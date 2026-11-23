import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { computed } from 'vue';
import { account } from '@/test/fixtures';
import { renderPage, screenWidth } from '@/test/page';
import Settings from './Settings.vue';

const bankFeed = vi.hoisted(() => ({
    share: null as { unsorted: number } | null,
}));

// What the server shares with every page is not part of the page: the test sets it here
vi.mock('@/composables/useBankFeed', () => ({
    useBankFeed: () => computed(() => bankFeed.share),
}));

afterEach(() => {
    vi.unstubAllGlobals();
    bankFeed.share = null;
});

describe('Settings', () => {
    it('leads to my account and to the categories', () => {
        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        expect(
            screen.getByRole('heading', { name: 'Settings' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /My account/ }),
        ).toHaveAttribute('href', '/settings/account');
        expect(
            screen.getByText('Marco Bianchi · Administrator'),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Calendar/ })).toHaveAttribute(
            'href',
            '/settings/calendar',
        );
        expect(
            screen.getByRole('link', { name: /Address for the bills/ }),
        ).toHaveAttribute('href', '/settings/mail');
        expect(
            screen.getByText('The bills arrive by email by themselves'),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Notices/ })).toHaveAttribute(
            'href',
            '/settings/notices',
        );
        expect(
            screen.getByText('How many days before, where and to whom'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Document categories/ }),
        ).toHaveAttribute('href', '/settings/categories');
        expect(screen.getByText('10 main categories')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Expense categories/ }),
        ).toHaveAttribute('href', '/settings/expense-categories');
        expect(screen.getByText('13 main categories')).toBeInTheDocument();
    });

    it('writes the singular for one main category', () => {
        renderPage(Settings, {
            account,
            mainCategories: 1,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        expect(screen.getByText('1 main category')).toBeInTheDocument();
    });

    it('leads to the areas, and says how many are hidden', () => {
        const view = renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        expect(screen.getByRole('link', { name: /Areas/ })).toHaveAttribute(
            'href',
            '/settings/areas',
        );
        expect(screen.getByText('9 areas')).toBeInTheDocument();

        view.unmount();
        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 2,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        expect(screen.getByText('9 areas, 2 hidden')).toBeInTheDocument();
    });

    it('leads to Support, in The family', () => {
        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        expect(screen.getByRole('link', { name: /Support/ })).toHaveAttribute(
            'href',
            '/settings/support',
        );
    });

    it('leads to the family name, first in The family, with the name', () => {
        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        const row = screen.getByRole('link', { name: /Family name/ });
        expect(row).toHaveAttribute('href', '/settings/family');
        expect(within(row).getByText('Bianchi')).toBeInTheDocument();
    });

    it('puts Areas after Expense categories, in The family', () => {
        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        const family = screen.getByRole('region', { name: 'The family' });
        expect(
            within(family)
                .getAllByRole('link')
                .map((link) => link.getAttribute('href')),
        ).toEqual([
            '/settings/family',
            '/settings/notices',
            '/settings/mail',
            '/settings/categories',
            '/settings/expense-categories',
            '/settings/areas',
            '/settings/support',
        ]);
    });

    it("has What's new with the version under The app", () => {
        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        const app = screen.getByRole('region', { name: 'The app' });
        expect(
            within(app).getByRole('link', { name: /What's new/ }),
        ).toHaveAttribute('href', '/settings/news');
        expect(
            within(app).getByText('Version 1.2.0 · 15/11/2026'),
        ).toBeInTheDocument();
    });

    it('writes the development version without a day', () => {
        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            release: 'dev',
            releasedOn: null,
        });

        expect(screen.getByText('Development version')).toBeInTheDocument();
    });

    it('opens My account in the wide layout, where the sections stand beside it', () => {
        screenWidth(1280);
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);

        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        expect(visit).toHaveBeenCalledWith(
            '/settings/account',
            expect.objectContaining({ replace: true }),
        );
        visit.mockRestore();
    });

    it('shows the linked banks only while the link to the bank is on', () => {
        const props = {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            bankCount: 2,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        };

        const off = renderPage(Settings, props);
        expect(
            screen.queryByRole('link', { name: /Linked banks/ }),
        ).not.toBeInTheDocument();
        off.unmount();

        bankFeed.share = { unsorted: 0 };
        renderPage(Settings, props);

        expect(
            screen.getByRole('link', { name: /Linked banks/ }),
        ).toHaveAttribute('href', '/settings/banks');
        expect(screen.getByText('2 banks linked')).toBeInTheDocument();
    });

    it('invites to link the first bank when there is none', () => {
        bankFeed.share = { unsorted: 0 };

        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
            bankCount: 0,
            release: '1.2.0',
            releasedOn: '2026-11-15',
        });

        expect(
            screen.getByText('Receive the transactions by themselves'),
        ).toBeInTheDocument();
    });
});
