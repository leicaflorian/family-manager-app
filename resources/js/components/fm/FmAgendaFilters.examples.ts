/**
 * The cases of `FmAgendaFilters` for the component catalogue: the toolbar of
 * the computer, also with a person and an area turned off, and the buttons of
 * the phone with their panel (opened with *Open* on the catalogue page), and
 * the same two for *Reminders*: the lists in place of the period, and the search.
 */
import { defineExamples } from '@/catalog/examples';
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

export default defineExamples(FmAgendaFilters, [
    {
        name: 'Toolbar',
        props: {
            variant: 'toolbar',
            filters: { months: 1, person_id: null, hidden_areas: [] },
            people,
            areas: agendaAreas,
            periods: agendaPeriods,
            counts,
            shown: 12,
        },
    },
    {
        name: 'Toolbar, filtered',
        props: {
            variant: 'toolbar',
            filters: { months: 3, person_id: 2, hidden_areas: ['work'] },
            people,
            areas: agendaAreas,
            periods: agendaPeriods,
            counts,
            shown: 10,
        },
    },
    {
        name: 'Phone',
        props: {
            variant: 'sheet',
            open: false,
            filters: { months: 1, person_id: null, hidden_areas: [] },
            people,
            areas: agendaAreas,
            periods: agendaPeriods,
            counts,
            shown: 12,
        },
    },
    {
        name: 'Phone, filtered',
        props: {
            variant: 'sheet',
            open: false,
            filters: { months: 3, person_id: 2, hidden_areas: ['work'] },
            people,
            areas: agendaAreas,
            periods: agendaPeriods,
            counts,
            shown: 10,
        },
    },
    {
        name: 'Toolbar of Reminders',
        props: {
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
    },
    {
        name: 'Phone of Reminders',
        props: {
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
    },
]);
