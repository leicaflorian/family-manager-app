/**
 * The cases of `FmAgendaRow` for the component catalogue: the Agenda with a
 * payment, an income, a deadline with its reminder done and a passed one; the
 * Home with an upcoming payment and a late, estimated one.
 */
import { defineExamples } from '@/catalog/examples';
import { fromToday } from '@/catalog/dates';
import FmAgendaRow from './FmAgendaRow.vue';

/** An entry of the Agenda as the server sends it. */
const payment: App.Data.AgendaEntryData = {
    key: 'payment:gas',
    kind: 'payment',
    date: fromToday(4),
    title: 'Bolletta gas',
    subtitle: 'Gas - Enel',
    area: 'home',
    person_id: 1,
    person: 'Marco',
    amount: 84.2,
    estimated: false,
    reminder_done: false,
    href: '/budget/planned/expense/3/occurrences/2026-10-30',
};

export default defineExamples(FmAgendaRow, [
    { name: 'Agenda, payment', props: { entry: payment } },
    {
        name: 'Agenda, with initials',
        props: {
            entry: payment,
            personAvatar: { initials: 'MB', photo: null },
        },
    },
    {
        name: 'Agenda, income',
        props: {
            entry: {
                ...payment,
                kind: 'income',
                title: 'Stipendio di Marco',
                subtitle: 'Studio Rinaldi',
                area: 'work',
                amount: 1850,
                date: fromToday(1),
            },
        },
    },
    {
        name: 'Agenda, reminder done',
        props: {
            entry: {
                ...payment,
                kind: 'deadline',
                title: 'Scade la carta d’identità',
                subtitle: 'Documento di Marco',
                area: 'general',
                amount: null,
                reminder_done: true,
                date: fromToday(19),
            },
        },
    },
    {
        name: 'Agenda, passed',
        props: {
            entry: {
                ...payment,
                kind: 'deadline',
                title: 'Tessera sanitaria di Giulia',
                subtitle: 'Documento',
                area: 'general',
                person_id: 3,
                person: 'Giulia',
                amount: null,
                date: fromToday(-26),
            },
        },
    },
    {
        name: 'Home, upcoming',
        props: { entry: payment, layout: 'home' },
    },
    {
        name: 'Home, late and estimated',
        props: {
            entry: {
                ...payment,
                title: 'Bolletta luce di settembre',
                subtitle: 'Luce - Enel',
                amount: 71.3,
                estimated: true,
                date: fromToday(-3),
            },
            layout: 'home',
        },
    },
]);
