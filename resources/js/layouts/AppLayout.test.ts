import { render, screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/vue3';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { usePageTrail } from '@/composables/usePageTrail';
import { trackChanges } from '@/lib/unsavedChanges';
import { areaCounts } from '@/test/fixtures';
import { press, screenWidth } from '@/test/page';
import AppLayout from './AppLayout.vue';

const page = vi.hoisted(() => ({
    url: '/documents?person=3',
    component: 'Documents/Index',
    toSort: 0,
    impersonating: null as string | null,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        url: page.url,
        get component() {
            return page.component;
        },
        props: {
            auth: { user: { name: 'Marco Bianchi' } },
            family: { name: 'Bianchi' },
            areas: areaCounts,
            to_sort_count: page.toSort,
            impersonating: page.impersonating,
            unread_notices: 2,
        },
    }),
}));

/**
 * What Inertia fires after a visit to another address: `navigate` of type `visit`.
 */
function visited(): void {
    document.dispatchEvent(
        new CustomEvent('inertia:navigate', {
            detail: { page: {}, type: 'visit' },
        }),
    );
}

/** A page that writes its trail into the top bar. */
const PageWithTrail = defineComponent({
    setup() {
        usePageTrail(() => [
            { label: 'Documents', href: '/documents' },
            { label: 'Electricity bill' },
        ]);

        return () => h('h1', 'Electricity bill');
    },
});

describe('AppLayout', () => {
    beforeEach(() => screenWidth(1280));

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        page.component = 'Documents/Index';
        page.url = '/documents?person=3';
    });

    it('on the phone has the bottom bar, and neither the top bar nor the sidebar', () => {
        screenWidth(390);
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        expect(
            screen.getByRole('button', { name: 'More' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('navigation', { name: 'Where you are' }),
        ).not.toBeInTheDocument();
        expect(screen.queryByText('Bianchi family')).not.toBeInTheDocument();
        expect(
            screen.getAllByRole('navigation', { name: 'Sections' }),
        ).toHaveLength(1);
    });

    it('on the tablet has the Menu button, and neither the bottom bar nor the sidebar', () => {
        screenWidth(820);
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        expect(screen.getByRole('button', { name: 'Menu' })).toHaveAttribute(
            'aria-expanded',
            'false',
        );
        expect(
            screen.queryByRole('button', { name: 'More' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('navigation', { name: 'Sections' }),
        ).not.toBeInTheDocument();
    });

    it('on the tablet opens the menu with the items of the sidebar, and a visit closes it', async () => {
        screenWidth(820);
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        await userEvent.click(screen.getByRole('button', { name: 'Menu' }));

        const menu = await screen.findByRole('dialog', { name: 'Menu' });
        expect(menu).toHaveAttribute('id', 'app-menu');
        expect(
            within(menu).getByRole('link', { name: 'Documents' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            // The open dialog hides the rest of the page from screen readers
            screen.getByRole('button', { name: 'Menu', hidden: true }),
        ).toHaveAttribute('aria-expanded', 'true');

        visited();

        await waitFor(() =>
            expect(
                screen.queryByRole('dialog', { name: 'Menu' }),
            ).not.toBeInTheDocument(),
        );
    });

    it('on the computer has the sidebar and the trail, with the section of the page', () => {
        page.component = 'documents/Documents';
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        const sidebar = screen.getByRole('navigation', { name: 'Sections' });
        expect(
            within(sidebar).getByRole('link', { name: 'Documents' }),
        ).toHaveAttribute('aria-current', 'page');
        const trail = screen.getByRole('navigation', { name: 'Where you are' });
        expect(trail).toHaveTextContent('Documents');
        expect(
            screen.queryByRole('button', { name: 'Back' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Back' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'More' }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('main')).toHaveTextContent('Documents');
    });

    it('shows the trail written by the page', async () => {
        page.component = 'documents/Document';
        page.url = '/documents/7';
        render(AppLayout, { slots: { default: PageWithTrail } });

        const trail = await screen.findByRole('navigation', {
            name: 'Where you are',
        });
        expect(
            await within(trail).findByRole('link', { name: 'Documents' }),
        ).toHaveAttribute('href', '/documents');
        expect(trail).toHaveTextContent('Electricity bill');
        expect(screen.getByRole('link', { name: 'Back' })).toHaveAttribute(
            'href',
            '/documents',
        );
    });

    it('focuses the box of the top bar with ⌘K on the computer', () => {
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        const event = press({ key: 'k', metaKey: true });

        expect(
            screen.getByRole('combobox', { name: 'Search in Family Manager' }),
        ).toHaveFocus();
        expect(event.defaultPrevented).toBe(true);
        expect(visit).not.toHaveBeenCalled();
    });

    it('focuses the box of the top bar with ⌘K on the Home of the tablet', () => {
        screenWidth(820);
        page.component = 'Home';
        render(AppLayout, { slots: { default: '<h1>Home</h1>' } });

        press({ key: 'k', metaKey: true });

        expect(
            screen.getByRole('combobox', { name: 'Search in Family Manager' }),
        ).toHaveFocus();
    });

    it('opens the Search page with ⌘K on the phone', () => {
        screenWidth(390);
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        const event = press({ key: 'k', metaKey: true });

        expect(visit).toHaveBeenCalledWith('/search');
        expect(event.defaultPrevented).toBe(true);
    });

    it('focuses the box of the Search page with ⌘K on the phone', () => {
        screenWidth(390);
        page.component = 'Search';
        page.url = '/search?q=Panda';
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        render(AppLayout, {
            slots: {
                default:
                    '<input id="search-page-field" aria-label="Search the page" />',
            },
        });

        press({ key: 'k', metaKey: true });

        expect(screen.getByLabelText('Search the page')).toHaveFocus();
        expect(visit).not.toHaveBeenCalled();
    });

    it('puts the text of the address in the box of the top bar on the Search page', () => {
        page.component = 'Search';
        page.url = '/search?q=Panda';
        render(AppLayout, { slots: { default: '<h1>Search</h1>' } });

        expect(
            screen.getByRole('combobox', { name: 'Search in Family Manager' }),
        ).toHaveValue('Panda');
    });

    it('loads the notices when the bell opens', async () => {
        const reload = vi
            .spyOn(router, 'reload')
            .mockImplementation(() => undefined);
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        await userEvent.click(
            screen.getByRole('button', { name: 'Notices: 2 new' }),
        );

        expect(reload).toHaveBeenCalledWith({
            only: ['bell_notices'],
            preserveUrl: true,
        });
    });

    it('asks before ⌘K leaves a form not saved on the phone', async () => {
        screenWidth(390);
        const stop = trackChanges(() => true);
        const view = render(AppLayout, {
            slots: { default: '<h1>Edit the reminder</h1>' },
        });

        press({ key: 'k', metaKey: true });

        const question = await screen.findByRole('dialog', {
            name: 'Leave without saving?',
        });
        await userEvent.click(
            within(question).getByRole('button', { name: 'Cancel' }),
        );

        stop();
        view.unmount();
    });

    it('shows the files to sort on To sort in the sidebar and on the + of the bottom bar', () => {
        page.toSort = 4;
        const view = render(AppLayout, {
            slots: { default: '<h1>Documents</h1>' },
        });

        expect(screen.getByRole('link', { name: 'To sort 4' })).toHaveAttribute(
            'href',
            '/to-sort',
        );
        view.unmount();
        screenWidth(390);
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });
        page.toSort = 0;
        expect(
            screen.getByRole('button', { name: 'Add, 4 to sort' }),
        ).toBeInTheDocument();
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
