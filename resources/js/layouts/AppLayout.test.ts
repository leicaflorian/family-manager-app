import { render, screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/vue3';
import { config, mount } from '@vue/test-utils';
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
import { formPlugins } from '@/lib/formPlugins';
import { areaCounts } from '@/test/fixtures';
import { press, screenWidth } from '@/test/page';
import { captureScreenshot } from '@/lib/screenshot';
import FmFeedbackSheet from '@/components/fm/FmFeedbackSheet.vue';
import AppLayout from './AppLayout.vue';

vi.mock('@/lib/screenshot', () => ({ captureScreenshot: vi.fn() }));

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
            name: 'Fameja.app',
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
            detail: { page: { url: '/areas' }, type: 'visit' },
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

// The layout keeps the panel *Report a problem or an idea*, a FormKit form
config.global.plugins = [...config.global.plugins, ...formPlugins];

/** Opens *Report a problem or an idea* from the menu of the account. */
async function openReport(wrapper: ReturnType<typeof mount>): Promise<void> {
    await wrapper.find('button[aria-haspopup="menu"]').trigger('click');
    await userEvent.click(
        await screen.findByRole('menuitem', {
            name: 'Report a problem or an idea',
        }),
    );
}

describe('AppLayout', () => {
    beforeEach(() => {
        screenWidth(1280);
        vi.mocked(captureScreenshot).mockReset();
        vi.mocked(captureScreenshot).mockResolvedValue(null);
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        page.component = 'Documents/Index';
        page.url = '/documents?person=3';
        page.toSort = 0;
    });

    it('tells the pages how high its sticky top is: the top bar, nothing on the phone', () => {
        const computer = render(AppLayout, {
            slots: { default: '<h1>Documents</h1>' },
        });
        expect(
            (
                computer.container.firstElementChild as HTMLElement
            ).style.getPropertyValue('--app-header'),
        ).toBe('var(--spacing-topbar)');
        computer.unmount();

        screenWidth(390);
        const phone = render(AppLayout, {
            slots: { default: '<h1>Documents</h1>' },
        });
        expect(
            (
                phone.container.firstElementChild as HTMLElement
            ).style.getPropertyValue('--app-header'),
        ).toBe('0px');
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

    it('on the tablet closes the menu also for a visit to the page already open', async () => {
        screenWidth(820);
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });
        await userEvent.click(screen.getByRole('button', { name: 'Menu' }));
        await screen.findByRole('dialog', { name: 'Menu' });

        // the same address fires no navigate: only the start of the visit
        document.dispatchEvent(
            new CustomEvent('inertia:start', {
                detail: {
                    visit: {
                        url: new URL('/documents', window.location.origin),
                        only: [],
                        except: [],
                        prefetch: false,
                    },
                },
            }),
        );

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
            screen.getByRole('combobox', { name: 'Search in Fameja.app' }),
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
            screen.getByRole('combobox', { name: 'Search in Fameja.app' }),
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
            screen.getByRole('combobox', { name: 'Search in Fameja.app' }),
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

    it('opens Report a problem or an idea from My account on the computer', async () => {
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        await userEvent.click(
            screen.getByRole('button', { name: /Marco Bianchi/ }),
        );
        await userEvent.click(
            screen.getByRole('menuitem', {
                name: 'Report a problem or an idea',
            }),
        );

        expect(
            await screen.findByRole('dialog', {
                name: 'Report a problem or an idea',
            }),
        ).toBeInTheDocument();
    });

    it('opens the report panel at once and hands it the photo of the page when ready', async () => {
        const photo = new File(['x'], 'page.webp', { type: 'image/webp' });
        let finish: (file: File | null) => void = () => {};
        vi.mocked(captureScreenshot).mockReturnValue(
            new Promise((resolve) => {
                finish = resolve;
            }),
        );
        const wrapper = mount(AppLayout, {
            slots: { default: '<h1>Documents</h1>' },
            attachTo: document.body,
        });
        await openReport(wrapper);

        expect(
            await screen.findByRole('dialog', {
                name: 'Report a problem or an idea',
            }),
        ).toBeInTheDocument();
        expect(captureScreenshot).toHaveBeenCalledTimes(1);
        const sheet = wrapper.findComponent(FmFeedbackSheet);
        expect(sheet.props('screenshot')).toBeNull();

        finish(photo);
        await waitFor(() => expect(sheet.props('screenshot')).toBe(photo));

        wrapper.unmount();
    });

    it('starts every opening of the report panel without the previous photo, and ignores a late one', async () => {
        const photo = new File(['x'], 'page.webp', { type: 'image/webp' });
        let late: (file: File | null) => void = () => {};
        vi.mocked(captureScreenshot)
            .mockResolvedValueOnce(photo)
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    late = resolve;
                }),
            );
        const wrapper = mount(AppLayout, {
            slots: { default: '<h1>Documents</h1>' },
            attachTo: document.body,
        });
        const sheet = wrapper.findComponent(FmFeedbackSheet);

        await openReport(wrapper);
        await waitFor(() => expect(sheet.props('screenshot')).toBe(photo));
        await userEvent.keyboard('{Escape}');
        await waitFor(() =>
            expect(
                screen.queryByRole('dialog', {
                    name: 'Report a problem or an idea',
                }),
            ).not.toBeInTheDocument(),
        );

        await openReport(wrapper);
        expect(sheet.props('screenshot')).toBeNull();

        await userEvent.keyboard('{Escape}');
        late(photo);
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(sheet.props('screenshot')).toBeNull();

        wrapper.unmount();
    });

    it('opens Report a problem or an idea from More on the phone, in place of More', async () => {
        screenWidth(390);
        render(AppLayout, { slots: { default: '<h1>Documents</h1>' } });

        await userEvent.click(screen.getByRole('button', { name: 'More' }));
        await userEvent.click(
            within(screen.getByRole('dialog', { name: 'More' })).getByRole(
                'button',
                { name: /^Report a problem or an idea/ },
            ),
        );

        expect(
            await screen.findByRole('dialog', {
                name: 'Report a problem or an idea',
            }),
        ).toBeInTheDocument();
        await waitFor(() =>
            expect(
                screen.queryByRole('dialog', { name: 'More' }),
            ).not.toBeInTheDocument(),
        );
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
