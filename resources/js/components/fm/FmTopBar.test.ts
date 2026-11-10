import { router } from '@inertiajs/vue3';
import { render, screen, type RenderResult } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, h, ref, type VNode } from 'vue';
import { formPlugins } from '@/lib/formPlugins';
import { renderPage } from '@/test/page';
import * as visitHistory from '@/lib/visitHistory';
import FmTopBar from './FmTopBar.vue';

const three = [
    { label: 'Areas', href: '/areas' },
    { label: 'Vehicles', href: '/areas/vehicles' },
    { label: 'Fiat Panda' },
];

/** The props of the bar of the computer on an inner page. */
const computer = {
    mode: 'computer' as const,
    trail: three,
    section: false,
    family: 'Bianchi',
    unreadNotices: 0,
};

/**
 * Mounts the bar with the given props.
 *
 * @param props - the props of the bar
 * @returns the result of Testing Library
 */
function renderBar(props: Record<string, unknown>): RenderResult {
    return renderPage(FmTopBar, props);
}

describe('FmTopBar', () => {
    beforeEach(() => {
        // no first results: the menu stays closed
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({ query: '', total: 0 }),
                }),
            ),
        );
        vi.spyOn(visitHistory, 'canGoBack').mockReturnValue(false);
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('shows Back to the piece above, the trail, the search and the bell on the computer', () => {
        renderBar(computer);

        expect(screen.getByRole('link', { name: 'Back' })).toHaveAttribute(
            'href',
            '/areas/vehicles',
        );
        expect(
            screen.getByRole('navigation', { name: 'Where you are' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Fiat Panda')).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(
            screen.getByRole('combobox', { name: 'Search in Family Manager' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Notices' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('banner')).toHaveClass('h-topbar', 'px-10');
    });

    it('has no Back on the page of a section', () => {
        renderBar({ ...computer, section: true });

        expect(screen.queryByRole('link', { name: 'Back' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Back' })).toBeNull();
    });

    it.each([['Meta'], ['Control']])(
        'leaves a click on Back with %s to the browser',
        async (key) => {
            vi.mocked(visitHistory.canGoBack).mockReturnValue(true);
            const back = vi
                .spyOn(window.history, 'back')
                .mockImplementation(() => undefined);
            const visit = vi
                .spyOn(router, 'visit')
                .mockImplementation(() => undefined);
            renderBar(computer);
            const link = screen.getByRole('link', { name: 'Back' });
            // jsdom would try to follow the link: stop it after the bar has seen the click
            link.addEventListener('click', (event) => event.preventDefault());

            // the same session keeps the key held during the click
            const user = userEvent.setup();
            await user.keyboard(`{${key}>}`);
            await user.click(link);
            await user.keyboard(`{/${key}}`);

            expect(back).not.toHaveBeenCalled();
            expect(visit).not.toHaveBeenCalled();
        },
    );

    it('shows Back as a button that goes back without a page above', async () => {
        vi.mocked(visitHistory.canGoBack).mockReturnValue(true);
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => undefined);
        renderBar({ ...computer, trail: [{ label: 'Search' }] });

        expect(screen.queryByRole('link', { name: 'Back' })).toBeNull();
        await userEvent.click(screen.getByRole('button', { name: 'Back' }));

        expect(back).toHaveBeenCalledOnce();
    });

    it('goes back in the history when a page of the app comes before', async () => {
        vi.mocked(visitHistory.canGoBack).mockReturnValue(true);
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => undefined);
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        renderBar(computer);

        await userEvent.click(screen.getByRole('link', { name: 'Back' }));

        expect(back).toHaveBeenCalledOnce();
        expect(visit).not.toHaveBeenCalled();
    });

    it('visits the piece above when no page of the app comes before', async () => {
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => undefined);
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        renderBar(computer);

        await userEvent.click(screen.getByRole('link', { name: 'Back' }));

        expect(visit).toHaveBeenCalledWith('/areas/vehicles');
        expect(back).not.toHaveBeenCalled();
    });

    it('has no Back with a single piece and no page before', () => {
        renderBar({ ...computer, trail: [{ label: 'Search' }] });

        expect(screen.queryByRole('link', { name: 'Back' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Back' })).toBeNull();
    });

    it('shows the menu button, the logo and the family on the tablet', async () => {
        const bar = renderBar({
            ...computer,
            mode: 'tablet',
            uploadCount: 5,
            menuOpen: false,
        });

        const menu = screen.getByRole('button', {
            name: 'Menu, 5 files to sort',
        });
        expect(menu).toHaveAttribute('aria-expanded', 'false');
        expect(menu).toHaveAttribute('aria-controls', 'app-menu');
        expect(screen.getByText('Family Manager')).toBeInTheDocument();
        expect(screen.getByText('Bianchi family')).toBeInTheDocument();
        expect(screen.queryByRole('navigation')).toBeNull();
        expect(screen.queryByRole('link', { name: 'Back' })).toBeNull();
        expect(screen.getByRole('banner')).toHaveClass('px-8');

        await userEvent.click(menu);

        expect(bar.emitted('openMenu')).toHaveLength(1);
    });

    it('calls the menu button only «Menu» without files to sort', () => {
        renderBar({ ...computer, mode: 'tablet', menuOpen: true });

        expect(screen.getByRole('button', { name: 'Menu' })).toHaveAttribute(
            'aria-expanded',
            'true',
        );
    });

    it('searches the text on the Search page with Enter', async () => {
        const visit = vi
            .spyOn(router, 'visit')
            .mockImplementation(() => undefined);
        renderBar(computer);

        await userEvent.type(
            screen.getByRole('combobox', { name: 'Search in Family Manager' }),
            'Pan{Enter}',
        );

        expect(visit).toHaveBeenCalledWith('/search?q=Pan');
    });

    it('puts the query of the Search page in the box', () => {
        renderBar({ ...computer, query: 'Panda' });

        expect(
            screen.getByRole('combobox', { name: 'Search in Family Manager' }),
        ).toHaveValue('Panda');
    });

    it('asks for the notices when the bell opens', async () => {
        const bar = renderBar(computer);

        await userEvent.click(screen.getByRole('button', { name: 'Notices' }));

        expect(bar.emitted('loadNotices')).toHaveLength(1);
    });

    it('opens the notices 8 px under its line, 20 px under the bell', async () => {
        render(FmTopBar, {
            props: computer,
            global: {
                plugins: formPlugins,
                stubs: {
                    PopoverContent: {
                        props: ['sideOffset'],
                        template:
                            '<div data-testid="panel" :data-side-offset="sideOffset"><slot /></div>',
                    },
                },
            },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Notices' }));

        expect(screen.getByTestId('panel')).toHaveAttribute(
            'data-side-offset',
            '20',
        );
    });

    it('puts the focus in the box with focusSearch', async () => {
        // the layout holds the bar in a template ref and calls the method
        const Layout = defineComponent({
            setup() {
                const bar = ref<InstanceType<typeof FmTopBar> | null>(null);

                return (): VNode[] => [
                    h(FmTopBar, { ...computer, ref: bar }),
                    h(
                        'button',
                        {
                            type: 'button',
                            onClick: () => bar.value?.focusSearch(),
                        },
                        'Focus',
                    ),
                ];
            },
        });
        render(Layout, { global: { plugins: formPlugins } });

        await userEvent.click(screen.getByRole('button', { name: 'Focus' }));

        expect(
            screen.getByRole('combobox', { name: 'Search in Family Manager' }),
        ).toHaveFocus();
    });
});
