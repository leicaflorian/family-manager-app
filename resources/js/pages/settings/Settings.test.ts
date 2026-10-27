import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { account } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Settings from './Settings.vue';

describe('Settings', () => {
    it('leads to my account and to the categories', () => {
        renderPage(Settings, { account, mainCategories: 10 });

        expect(
            screen.getByRole('heading', { name: 'Settings' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /My account/ }),
        ).toHaveAttribute('href', '/settings/account');
        expect(
            screen.getByText('Marco Bianchi · Administrator'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Document categories/ }),
        ).toHaveAttribute('href', '/settings/categories');
        expect(screen.getByText('10 main categories')).toBeInTheDocument();
    });

    it('writes the singular for one main category', () => {
        renderPage(Settings, { account, mainCategories: 1 });

        expect(screen.getByText('1 main category')).toBeInTheDocument();
    });
});
