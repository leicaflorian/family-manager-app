import userEvent from '@testing-library/user-event';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import type { ComponentProps } from 'vue-component-type-helpers';
import { agendaAreas } from '@/test/fixtures';
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
        variant: 'column',
        filters: { months: 1, person_id: null, hidden_areas: [] },
        people,
        areas: agendaAreas,
        counts: { home: 3, work: 2, general: 2 },
        shown: 10,
        ...change,
    };
}

describe('FmAgendaFilters', () => {
    it('shows every area with its count', () => {
        render(FmAgendaFilters, { props: props() });

        expect(screen.getAllByRole('button', { pressed: true })).toHaveLength(
            10,
        );
        expect(screen.getByRole('button', { name: /Home/ })).toHaveTextContent(
            '3',
        );
        expect(
            screen.getByRole('button', { name: /Vehicles/ }),
        ).toHaveTextContent('0');
    });

    it('turns an area off and keeps its count', async () => {
        const view = render(FmAgendaFilters, { props: props() });

        await userEvent.click(screen.getByRole('button', { name: /Work/ }));

        expect(view.emitted().area).toEqual([['work']]);
    });

    it('shows an area turned off as not pressed, with its count', () => {
        render(FmAgendaFilters, {
            props: props({
                filters: { months: 1, person_id: null, hidden_areas: ['work'] },
            }),
        });

        const work = screen.getByRole('button', { name: /Work/ });
        expect(work).toHaveAttribute('aria-pressed', 'false');
        expect(work).toHaveTextContent('2');
    });

    it('chooses the period', async () => {
        const view = render(FmAgendaFilters, { props: props() });

        expect(screen.getByRole('radio', { name: '1 month' })).toBeChecked();

        await userEvent.click(screen.getByRole('radio', { name: '3 months' }));

        expect(view.emitted().months).toEqual([[3]]);
    });

    it('chooses a person, or the whole family', async () => {
        const view = render(FmAgendaFilters, {
            props: props({
                filters: { months: 1, person_id: 2, hidden_areas: [] },
            }),
        });

        expect(screen.getByRole('radio', { name: /Laura/ })).toBeChecked();

        await userEvent.click(screen.getByRole('radio', { name: /Marco/ }));
        await userEvent.click(
            screen.getByRole('radio', { name: /Whole family/ }),
        );

        expect(view.emitted().person).toEqual([[1], [null]]);
    });

    it('takes the filters off', async () => {
        const view = render(FmAgendaFilters, { props: props() });

        await userEvent.click(
            screen.getByRole('button', { name: 'Clear filters' }),
        );

        expect(view.emitted().reset).toHaveLength(1);
    });

    it('is a panel on the phone, ending with the entries it shows', async () => {
        const view = render(FmAgendaFilters, {
            props: props({ variant: 'sheet', open: true }),
        });

        const dialog = await screen.findByRole('dialog', {
            name: 'Filter the agenda',
        });
        expect(dialog).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Show 10 entries' }),
        );

        expect(view.emitted()['update:open']).toEqual([[false]]);
    });

    it('is closed on the phone until it is opened', () => {
        render(FmAgendaFilters, {
            props: props({ variant: 'sheet', open: false }),
        });

        expect(screen.queryByRole('dialog')).toBeNull();
    });
});
