import { router } from '@inertiajs/vue3';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import FmTitleTools from './FmTitleTools.vue';

const shared = vi.hoisted(() => ({ unread_notices: 2 }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: { ...shared } }),
}));

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmTitleTools', () => {
    it('has the round magnifier that leads to Search', () => {
        render(FmTitleTools);

        const link = screen.getByRole('link', {
            name: 'Search in Family Manager',
        });
        expect(link).toHaveAttribute('href', '/search');
        expect(link).toHaveClass('size-11', 'rounded-full', 'bg-card');
    });

    it('has the round bell with how many notices are new', () => {
        render(FmTitleTools);

        expect(
            screen.getByRole('button', { name: 'Notices: 2 new' }),
        ).toHaveClass('rounded-full', 'bg-card');
    });

    it('asks only for the notices when the bell opens, without changing the address', async () => {
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation(() => undefined);
        render(FmTitleTools);

        await userEvent.click(screen.getByRole('button', { name: /Notices/ }));

        expect(reload).toHaveBeenCalledWith({
            only: ['bell_notices'],
            preserveUrl: true,
        });
    });
});
