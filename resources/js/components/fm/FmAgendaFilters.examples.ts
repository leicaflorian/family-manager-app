/**
 * The cases of `FmAgendaFilters` for the component catalogue: the toolbar of
 * the computer, also with a person and an area turned off, and the buttons of
 * the phone with their panel (opened with *Open* on the catalogue page).
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
]);
