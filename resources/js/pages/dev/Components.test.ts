import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import Components from './Components.vue';

// The real list changes as the components get their `@group`: the page is
// tested on a small one of its own.
vi.mock('@/catalog/catalog.json', () => ({
    default: [
        {
            name: 'FmButton',
            path: 'resources/js/components/fm/FmButton.vue',
            group: 'basics',
            description: 'The button of the canvas.',
            props: [],
            events: ['click'],
            slots: ['default'],
            types: [],
            usedBy: [],
        },
        {
            name: 'FmPaySheet',
            path: 'resources/js/components/fm/FmPaySheet.vue',
            group: null,
            description: 'The panel that pays a deadline.',
            props: [],
            events: [],
            slots: [],
            types: [],
            usedBy: [],
        },
        {
            name: 'FmRow',
            path: 'resources/js/components/fm/FmRow.vue',
            group: 'rows',
            description: 'A list row of the canvas.',
            props: [
                {
                    name: 'title',
                    type: 'string',
                    required: true,
                    default: null,
                    description: 'The main text.',
                },
                {
                    name: 'tone',
                    type: 'RowTone',
                    required: false,
                    default: "'normal'",
                    description: 'The colour of the subtitle.',
                },
            ],
            events: ['click'],
            slots: ['before', 'after'],
            types: [],
            usedBy: ['pages/Areas.vue', 'pages/Home.vue'],
        },
        {
            name: 'FmAutopay',
            path: 'resources/js/components/fm/FmAutopay.vue',
            group: 'rows',
            description: 'A payment that goes by itself.',
            props: [],
            events: [],
            slots: [],
            types: [],
            usedBy: [],
        },
    ],
}));

/**
 * The column of the groups on the left.
 *
 * @returns The navigation with the groups and their components.
 */
function sidebar(): HTMLElement {
    return screen.getByRole('navigation', { name: 'Components' });
}

/**
 * The names of the components listed on the left, in order.
 *
 * @returns The text of each link.
 */
function listed(): string[] {
    // A long name is drawn in two pieces hidden from screen readers, beside the whole one
    return within(sidebar())
        .getAllByRole('link')
        .map((link) => {
            const copy = link.cloneNode(true) as HTMLElement;
            copy.querySelectorAll('[aria-hidden="true"]').forEach((piece) =>
                piece.remove(),
            );

            return copy.textContent?.trim() ?? '';
        });
}

describe('Components', () => {
    it('lists the components under the title of their group, the ungrouped ones last', () => {
        renderPage(Components, { selected: null });

        const titles = within(sidebar())
            .getAllByRole('heading')
            .map((heading) => heading.textContent?.trim());
        expect(titles).toEqual(['Basics', 'Rows', 'Ungrouped']);

        const rows = within(sidebar())
            .getByRole('heading', { name: 'Rows' })
            .closest('section')!;
        expect(within(rows).getAllByRole('link')).toEqual([
            within(rows).getByRole('link', { name: 'FmAutopay' }),
            within(rows).getByRole('link', { name: 'FmRow' }),
        ]);
        expect(listed()).toContain('FmPaySheet');
    });

    it('keeps only the names with the text searched, whatever the case', async () => {
        renderPage(Components, { selected: null });

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search a component' }),
            'pay',
        );
        await settle();

        expect(listed()).toEqual(['FmAutopay', 'FmPaySheet']);
        expect(
            within(sidebar()).queryByRole('heading', { name: 'Basics' }),
        ).not.toBeInTheDocument();
    });

    it('says so when no name has the text searched', async () => {
        renderPage(Components, { selected: null });

        await userEvent.type(
            screen.getByRole('searchbox', { name: 'Search a component' }),
            'zzz',
        );
        await settle();

        expect(
            screen.getByText('No component matches «zzz».'),
        ).toBeInTheDocument();
        expect(within(sidebar()).queryAllByRole('link')).toEqual([]);
    });

    it('asks to choose a component until one is chosen', () => {
        renderPage(Components, { selected: null });

        expect(
            screen.getByText('Choose a component on the left.'),
        ).toBeInTheDocument();
    });

    it('shows the chosen component: name, description, props, where it is used and its cases', () => {
        renderPage(Components, { selected: 'FmRow' });

        expect(
            screen.getByRole('heading', { level: 2, name: 'FmRow' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('A list row of the canvas.'),
        ).toBeInTheDocument();

        const table = screen.getByRole('table', { name: 'Props' });
        expect(
            within(table)
                .getAllByRole('columnheader')
                .map((cell) => cell.textContent?.trim()),
        ).toEqual(['Name', 'Type', 'Required', 'Default', 'Description']);
        const [, title, tone] = within(table).getAllByRole('row');
        expect(
            within(title!)
                .getAllByRole('cell')
                .map((cell) => cell.textContent?.trim()),
        ).toEqual(['title', 'string', 'Yes', '—', 'The main text.']);
        expect(
            within(tone!)
                .getAllByRole('cell')
                .map((cell) => cell.textContent?.trim()),
        ).toEqual([
            'tone',
            'RowTone',
            'No',
            "'normal'",
            'The colour of the subtitle.',
        ]);

        const usedBy = screen
            .getByRole('heading', { name: 'Used by' })
            .closest('section')!;
        expect(within(usedBy).getByText('pages/Areas.vue')).toBeInTheDocument();
        expect(within(usedBy).getByText('pages/Home.vue')).toBeInTheDocument();

        // The cases of `DevCases`, from the real `FmRow.examples.ts`
        expect(
            screen.getByRole('heading', { name: 'Overdue' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('log', { name: 'Events' })).toBeInTheDocument();
        expect(
            within(sidebar()).getByRole('link', { name: 'FmRow' }),
        ).toHaveAttribute('aria-current', 'page');
    });

    it('says when the chosen component is not used yet', () => {
        renderPage(Components, { selected: 'FmButton' });

        expect(screen.getByText('Not used yet.')).toBeInTheDocument();
    });

    it('shows the cases at phone width in a frame 390 px wide, and back at full width', async () => {
        renderPage(Components, { selected: 'FmRow' });

        await userEvent.click(
            await screen.findByRole('button', { name: 'Phone width' }),
        );
        await settle();

        const frame = screen.getByTitle('FmRow at phone width');
        expect(frame.tagName).toBe('IFRAME');
        expect(frame).toHaveAttribute('src', '/dev/components/FmRow');
        expect(frame).toHaveClass('w-[390px]');
        expect(
            screen.queryByRole('log', { name: 'Events' }),
        ).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Full width' }),
        );
        await settle();

        expect(
            screen.queryByTitle('FmRow at phone width'),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('log', { name: 'Events' })).toBeInTheDocument();
    });

    it('opens a component chosen on the left', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Components, { selected: null });

        await userEvent.click(
            within(sidebar()).getByRole('link', { name: 'FmButton' }),
        );

        expect(visit).toHaveBeenCalledWith(
            '/dev/components?component=FmButton',
            expect.objectContaining({ preserveState: true }),
        );
        visit.mockRestore();
    });
});
