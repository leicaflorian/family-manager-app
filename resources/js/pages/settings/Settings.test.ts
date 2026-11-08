import { screen } from '@testing-library/vue';
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
        });

        expect(screen.getByText('1 main category')).toBeInTheDocument();
    });
});
