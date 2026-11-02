import userEvent from '@testing-library/user-event';
import { render, screen, within } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import type { ComponentProps } from 'vue-component-type-helpers';
import { agendaAreas, agendaPeriods } from '@/test/fixtures';
import FmAgendaFilters from './FmAgendaFilters.vue';

const people = [
    { id: 1, first_name: 'Marco', initials: 'MB', photo: null },
    { id: 2, first_name: 'Laura', initials: 'LB', photo: null },
];

/** The props of the filters in use at the start: one month, everyone, every area. */
function props(
    change: Partial<ComponentProps<typeof FmAgendaFilters>> = {},
): ComponentProps<typeof FmAgendaFilters> {
    return {
        variant: 'toolbar',
        filters: { months: 1, person_id: null, hidden_areas: [] },
        people,
        areas: agendaAreas,
        periods: agendaPeriods,
        counts: { home: 3, work: 2, general: 2 },
        shown: 10,
        ...change,
    };
}

/** Opens the menu of the areas of the toolbar. */
async function openAreas(name: RegExp | string = 'All areas'): Promise<void> {
    await userEvent.click(screen.getByRole('button', { name }));
}

describe('FmAgendaFilters', () => {
    describe('the toolbar of the computer', () => {
        it('chooses the period', async () => {
            const view = render(FmAgendaFilters, { props: props() });

            expect(
                screen.getByRole('radio', { name: '1 month' }),
            ).toBeChecked();

            await userEvent.click(
                screen.getByRole('radio', { name: '3 months' }),
            );

            expect(view.emitted().months).toEqual([[3]]);
        });

        it('offers the periods the server sends, and no others', () => {
            render(FmAgendaFilters, { props: props({ periods: [1, 6] }) });

            expect(
                within(
                    screen.getByRole('radiogroup', { name: 'Period' }),
                ).getAllByRole('radio'),
            ).toHaveLength(2);
            expect(
                screen.queryByRole('radio', { name: '3 months' }),
            ).toBeNull();
        });

        it('chooses a person, or everyone', async () => {
            const view = render(FmAgendaFilters, {
                props: props({
                    filters: { months: 1, person_id: 2, hidden_areas: [] },
                }),
            });

            expect(screen.getByRole('radio', { name: /Laura/ })).toBeChecked();

            await userEvent.click(screen.getByRole('radio', { name: /Marco/ }));
            await userEvent.click(
                screen.getByRole('radio', { name: /Everyone/ }),
            );

            expect(view.emitted().person).toEqual([[1], [null]]);
        });

        it('shows every area with its count in the menu of the areas', async () => {
            render(FmAgendaFilters, { props: props() });

            await openAreas();

            expect(
                await screen.findAllByRole('button', { pressed: true }),
            ).toHaveLength(10);
            expect(
                screen.getByRole('button', { name: /Home/ }),
            ).toHaveTextContent('3');
            expect(
                screen.getByRole('button', { name: /Vehicles/ }),
            ).toHaveTextContent('0');
        });

        it('turns an area off', async () => {
            const view = render(FmAgendaFilters, { props: props() });

            await openAreas();
            await userEvent.click(
                await screen.findByRole('button', { name: /Work/ }),
            );

            expect(view.emitted().area).toEqual([['work']]);
        });

        it('says how many areas are off, and shows them not pressed with their count', async () => {
            render(FmAgendaFilters, {
                props: props({
                    filters: {
                        months: 1,
                        person_id: null,
                        hidden_areas: ['work'],
                    },
                }),
            });

            await openAreas('1 area off');

            const work = await screen.findByRole('button', { name: /Work/ });
            expect(work).toHaveAttribute('aria-pressed', 'false');
            expect(work).toHaveTextContent('2');
        });

        it('takes the filters off from the menu of the areas', async () => {
            const view = render(FmAgendaFilters, { props: props() });

            await openAreas();
            await userEvent.click(
                await screen.findByRole('button', { name: 'Clear filters' }),
            );

            expect(view.emitted().reset).toHaveLength(1);
        });
    });

    describe('the phone', () => {
        it('shows what is chosen as three buttons that open the panel', async () => {
            const view = render(FmAgendaFilters, {
                props: props({ variant: 'sheet', open: false }),
            });

            const group = screen.getByRole('group', { name: 'Filters' });
            expect(
                within(group)
                    .getAllByRole('button')
                    .map((button) => button.textContent?.trim()),
            ).toEqual(['1 month', 'Everyone', 'All areas']);

            await userEvent.click(
                within(group).getByRole('button', { name: /Everyone/ }),
            );

            expect(view.emitted()['update:open']).toEqual([[true]]);
        });

        it('names the person chosen and the areas off', () => {
            render(FmAgendaFilters, {
                props: props({
                    variant: 'sheet',
                    filters: {
                        months: 3,
                        person_id: 2,
                        hidden_areas: ['work', 'home'],
                    },
                }),
            });

            const group = screen.getByRole('group', { name: 'Filters' });
            expect(
                within(group)
                    .getAllByRole('button')
                    .map((button) => button.textContent?.trim()),
            ).toEqual(['3 months', 'LB Laura', '2 areas off']);
        });

        it('is a panel ending with the entries it shows', async () => {
            const view = render(FmAgendaFilters, {
                props: props({ variant: 'sheet', open: true }),
            });

            const dialog = await screen.findByRole('dialog', {
                name: 'Filter the agenda',
            });
            expect(
                within(dialog).getByRole('button', { name: /Work/ }),
            ).toHaveAttribute('aria-pressed', 'true');

            await userEvent.click(
                within(dialog).getByRole('button', { name: 'Show 10 entries' }),
            );

            expect(view.emitted()['update:open']).toEqual([[false]]);
        });

        it('is closed until it is opened', () => {
            render(FmAgendaFilters, {
                props: props({ variant: 'sheet', open: false }),
            });

            expect(screen.queryByRole('dialog')).toBeNull();
        });
    });

    describe('without the period, as in Reminders', () => {
        /** The props of *Reminders*: the two lists in place of the period, and the search. */
        const reminders = (
            change: Partial<ComponentProps<typeof FmAgendaFilters>> = {},
        ): ComponentProps<typeof FmAgendaFilters> =>
            props({
                periods: undefined,
                views: {
                    label: 'Status',
                    options: [
                        { value: 'open', label: 'To do · 3' },
                        { value: 'done', label: 'Completed' },
                    ],
                },
                view: 'open',
                searchLabel: 'Search a reminder',
                search: '',
                ...change,
            });

        it('chooses the list in place of the period, on the computer', async () => {
            const view = render(FmAgendaFilters, { props: reminders() });

            expect(
                screen.queryByRole('radiogroup', { name: 'Period' }),
            ).toBeNull();
            expect(
                screen.getByRole('radio', { name: 'To do · 3' }),
            ).toBeChecked();

            await userEvent.click(
                screen.getByRole('radio', { name: 'Completed' }),
            );

            expect(view.emitted()['update:view']).toEqual([['done']]);
        });

        it('searches while typing, on the computer', async () => {
            const view = render(FmAgendaFilters, { props: reminders() });

            await userEvent.type(
                screen.getByRole('searchbox', { name: 'Search a reminder' }),
                'a',
            );

            expect(view.emitted()['update:search']).toEqual([['a']]);
        });

        it('has no search without its label', () => {
            render(FmAgendaFilters, {
                props: reminders({ searchLabel: undefined }),
            });

            expect(screen.queryByRole('searchbox')).toBeNull();
        });

        it('shows the lists as buttons beside the person and the areas, on the phone', async () => {
            const view = render(FmAgendaFilters, {
                props: reminders({ variant: 'sheet', open: false }),
            });

            const group = screen.getByRole('group', { name: 'Filters' });
            expect(
                within(group)
                    .getAllByRole('button')
                    .map((button) => button.textContent?.trim()),
            ).toEqual(['To do · 3', 'Completed', 'Everyone', 'All areas']);
            expect(
                within(group).getByRole('button', { name: 'To do · 3' }),
            ).toHaveAttribute('aria-pressed', 'true');

            await userEvent.click(
                within(group).getByRole('button', { name: 'Completed' }),
            );

            expect(view.emitted()['update:view']).toEqual([['done']]);
            expect(view.emitted()['update:open']).toBeUndefined();
        });

        it('leaves the period out of the panel', async () => {
            render(FmAgendaFilters, {
                props: reminders({ variant: 'sheet', open: true }),
            });

            const dialog = await screen.findByRole('dialog', {
                name: 'Filter the agenda',
            });
            expect(within(dialog).queryByText('Period')).toBeNull();
            expect(
                within(dialog).getByRole('radiogroup', { name: 'Person' }),
            ).toBeInTheDocument();
        });
    });
});
