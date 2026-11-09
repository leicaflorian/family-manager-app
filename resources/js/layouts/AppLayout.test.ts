import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/vue3';
import { describe, expect, it, vi } from 'vite-plus/test';
import { trackChanges } from '@/lib/unsavedChanges';
import { areaCounts } from '@/test/fixtures';
import AppLayout from './AppLayout.vue';

const page = vi.hoisted(() => ({
    url: '/documents?person=3',
    toSort: 0,
    impersonating: null as string | null,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        url: page.url,
        props: {
            auth: { user: { name: 'Marco Bianchi' } },
            family: { name: 'Bianchi' },
            areas: areaCounts,
            to_sort_count: page.toSort,
            impersonating: page.impersonating,
        },
    }),
}));

describe('AppLayout', () => {
    it('shows the files to sort on Upload, in the sidebar and in the bottom bar', () => {
        page.toSort = 4;
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });
        page.toSort = 0;

        expect(
            screen.getByRole('link', { name: '4 files to sort' }),
        ).toHaveAttribute('href', '/to-sort');
        expect(
            screen.getByRole('link', { name: 'Upload a document, 4 to sort' }),
        ).toBeInTheDocument();
    });

    it('has the sidebar for the computer and the bottom bar for the phone, with the section of the page', () => {
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        const [sidebar, bottom] = screen.getAllByRole('navigation', {
            name: 'Sections',
        });
        expect(
            within(sidebar).getByRole('link', { name: 'Documents' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            // Documents lives in More on the phone, which stays marked
            within(bottom).getByRole('button', { name: 'More' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(screen.getByText('Bianchi family')).toBeInTheDocument();
        expect(screen.getByRole('main')).toHaveTextContent('Documents');
    });

    it('lists the areas in the sidebar, with the one of the page marked', () => {
        page.url = '/areas/vehicles?status=active';
        render(AppLayout, { slots: { default: '<h1>Vehicles</h1>' } });

        expect(
            screen.getByRole('link', { name: 'Vehicles 1' }),
        ).toHaveAttribute('aria-current', 'page');
        page.url = '/documents?person=3';
    });

    it('asks before leaving a page with a form not saved, and stays if the person says so', async () => {
        const stop = trackChanges(() => true);
        const view = render(AppLayout, {
            slots: { default: '<h1>Edit the reminder</h1>' },
        });

        router.visit('/agenda');

        const question = await screen.findByRole('dialog', {
            name: 'Leave without saving?',
        });
        expect(question).toHaveTextContent(
            'You have changes not saved: if you leave, you lose them.',
        );
        expect(
            within(question).getByRole('button', {
                name: 'Leave without saving',
            }),
        ).toBeInTheDocument();

        await userEvent.click(
            within(question).getByRole('button', { name: 'Cancel' }),
        );
        expect(
            screen.queryByRole('dialog', { name: 'Leave without saving?' }),
        ).not.toBeInTheDocument();

        stop();
        view.unmount();
    });

    it('shows the strip of a visit of the owner, and Leave sends a POST', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        page.impersonating = 'Laura Bianchi';
        render(AppLayout, { slots: { default: '<h1>Home</h1>' } });
        page.impersonating = null;

        expect(
            screen.getByText('You are looking as Laura Bianchi'),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Leave' }));

        expect(post).toHaveBeenCalledWith('/visit/leave');
        post.mockRestore();
    });

    it('has no strip outside a visit', () => {
        render(AppLayout, { slots: { default: '<h1>Home</h1>' } });

        expect(
            screen.queryByText(/You are looking as/),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Leave' }),
        ).not.toBeInTheDocument();
    });
});
