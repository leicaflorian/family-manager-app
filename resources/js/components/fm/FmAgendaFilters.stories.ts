/**
 * The stories of `FmAgendaFilters`: the toolbar of
 * the computer, also with a person and an area turned off, and the buttons of
 * the phone with their panel (opened with the `open` control), and
 * the same two for *Reminders*: the lists in place of the period, and the search;
 * and the toolbar with areas the family hid in the settings.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { agendaAreas, agendaPeriods } from '@/test/fixtures';
import FmAgendaFilters from './FmAgendaFilters.vue';

const people = [
    { id: 1, first_name: 'Marco', initials: 'MB', photo: null },
    { id: 2, first_name: 'Laura', initials: 'LB', photo: null },
    { id: 3, first_name: 'Giulia', initials: 'GB', photo: null },
];

const counts = {
    general: 2,
    home: 3,
    insurance: 1,
    banks: 2,
    work: 2,
    subscriptions: 2,
};

/** The two lists of *Reminders*. */
const views = {
    label: 'Stato',
    options: [
        { value: 'open', label: 'Da fare · 17' },
        { value: 'done', label: 'Fatti' },
    ],
};

const meta = {
    title: 'Forms/FmAgendaFilters',
    component: FmAgendaFilters,
} satisfies Meta<typeof FmAgendaFilters>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Toolbar: Story = {
    args: {
        variant: 'toolbar',
        filters: { months: 1, person_id: null, hidden_areas: [] },
        people,
        areas: agendaAreas,
        periods: agendaPeriods,
        counts,
        shown: 12,
    },
};

export const ToolbarFiltered: Story = {
    name: 'Toolbar, filtered',
    args: {
        variant: 'toolbar',
        filters: { months: 3, person_id: 2, hidden_areas: ['work'] },
        people,
        areas: agendaAreas,
        periods: agendaPeriods,
        counts,
        shown: 10,
    },
};

/** The family hid *Work* and *School*: *Work* stays in the menu because it still has entries, *School* does not. */
export const WithHiddenAreasOfTheFamily: Story = {
    name: 'With hidden areas of the family',
    args: {
        variant: 'toolbar',
        filters: { months: 1, person_id: null, hidden_areas: [] },
        people,
        areas: agendaAreas,
        periods: agendaPeriods,
        counts,
        familyHidden: ['work', 'school'],
        shown: 12,
    },
};

export const Phone: Story = {
    args: {
        variant: 'sheet',
        open: false,
        filters: { months: 1, person_id: null, hidden_areas: [] },
        people,
        areas: agendaAreas,
        periods: agendaPeriods,
        counts,
        shown: 12,
    },
};

export const PhoneFiltered: Story = {
    name: 'Phone, filtered',
    args: {
        variant: 'sheet',
        open: false,
        filters: { months: 3, person_id: 2, hidden_areas: ['work'] },
        people,
        areas: agendaAreas,
        periods: agendaPeriods,
        counts,
        shown: 10,
    },
};

export const ToolbarOfReminders: Story = {
    name: 'Toolbar of Reminders',
    args: {
        variant: 'toolbar',
        filters: { person_id: null, hidden_areas: [] },
        people,
        areas: agendaAreas,
        views,
        view: 'open',
        searchLabel: 'Cerca un promemoria',
        search: '',
        counts,
        shown: 17,
    },
};

export const PhoneOfReminders: Story = {
    name: 'Phone of Reminders',
    args: {
        variant: 'sheet',
        open: false,
        filters: { person_id: null, hidden_areas: [] },
        people,
        areas: agendaAreas,
        views,
        view: 'open',
        counts,
        shown: 17,
    },
};
