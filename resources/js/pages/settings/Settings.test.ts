import { screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { account } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Settings from './Settings.vue';

describe('Settings', () => {
    it('leads to my account and to the categories', () => {
        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
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
        });

        expect(screen.getByRole('link', { name: /Support/ })).toHaveAttribute(
            'href',
            '/settings/support',
        );
    });

    it('puts Areas after Expense categories, in The family', () => {
        renderPage(Settings, {
            account,
            mainCategories: 10,
            mainExpenseCategories: 13,
            areaCount: 9,
            hiddenAreas: 0,
        });

        const family = screen.getByRole('region', { name: 'The family' });
        expect(
            within(family)
                .getAllByRole('link')
                .map((link) => link.getAttribute('href')),
        ).toEqual([
            '/settings/notices',
            '/settings/mail',
            '/settings/categories',
            '/settings/expense-categories',
            '/settings/areas',
            '/settings/support',
        ]);
    });
});
