/**
 * The cases of `FmAgendaRow` for the component catalogue: the Agenda with a
 * payment, one due tomorrow, an income, a deadline with its notice coming, one
 * with its reminder done and a passed one with its buttons; the Home with an
 * upcoming payment and a late, estimated one.
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
    reminder: null,
    update_url: null,
    name: 'Gas - Enel',
    detail: 'Bolletta gas',
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
        name: 'Agenda, tomorrow',
        props: { entry: { ...payment, date: fromToday(1) } },
    },
    {
        name: 'Agenda, income',
        props: {
            entry: {
                ...payment,
                kind: 'income',
                title: 'Stipendio di Marco',
                subtitle: 'Studio Rinaldi',
                name: 'Marco - Studio Rinaldi',
                detail: 'Stipendio',
                area: 'work',
                amount: 1850,
                date: fromToday(9),
            },
        },
    },
    {
        name: 'Agenda, notice coming',
        props: {
            entry: {
                ...payment,
                kind: 'deadline',
                title: 'End of the free trial',
                subtitle: 'Netflix',
                name: 'Netflix',
                detail: 'End of the free trial',
                area: 'subscriptions',
                person_id: 2,
                person: 'Laura',
                amount: null,
                reminder: { state: 'open', next_notice: fromToday(1) },
                date: fromToday(2),
            },
            personAvatar: { initials: 'LB', photo: null },
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
                name: 'Carta d’identità di Marco',
                detail: 'Document expiry',
                area: 'general',
                amount: null,
                reminder_done: true,
                reminder: { state: 'done', next_notice: null },
                date: fromToday(12),
            },
        },
    },
    {
        name: 'Agenda, passed, to sort out',
        props: {
            entry: {
                ...payment,
                kind: 'deadline',
                title: 'Tessera sanitaria di Giulia',
                subtitle: 'Documento',
                name: 'Tessera sanitaria di Giulia',
                detail: 'Document expiry',
                area: 'general',
                person_id: 3,
                person: 'Giulia',
                amount: null,
                reminder: { state: 'late', next_notice: null },
                date: fromToday(-13),
            },
            personAvatar: { initials: 'GB', photo: null },
            actions: true,
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
