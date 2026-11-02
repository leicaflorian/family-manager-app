/**
 * The cases of `FmReminderRow` for the component catalogue, as the boards
 * draw them: a late automatic one postponed from its deadline, one written by
 * hand about a card, one written by hand about nothing, one for everyone with
 * the planned expense missing, one being marked done and one that is done.
 */
import { defineExamples } from '@/catalog/examples';
import { fromToday } from '@/catalog/dates';
import type { ReminderItem } from '@/lib/reminders';
import FmReminderRow from './FmReminderRow.vue';

/** A reminder written by hand about the house, due in two days. */
const reminder: ReminderItem = {
    id: 1,
    title: 'Chiamare l’idraulico per il preventivo del bagno',
    due_date: fromToday(2),
    postponed_from: null,
    done: false,
    manual: true,
    area: 'home',
    subject: { label: 'Casa di via Roma', href: '/cards/home/1' },
    person: { name: 'Laura', initials: 'LB', photo: null },
    next_notice_on: fromToday(1),
    missing_planned_expense: false,
    href: '/reminders/1/edit',
    edit_href: '/reminders/1/edit',
    card_href: null,
    can_delete: true,
};

/** The health card of Giulia: made by the app, late, postponed from its deadline. */
const late: ReminderItem = {
    ...reminder,
    id: 2,
    title: 'Rinnovare la tessera sanitaria',
    due_date: fromToday(-13),
    postponed_from: fromToday(-13),
    manual: false,
    area: 'general',
    subject: { label: 'Tessera sanitaria di Giulia', href: '/documents/4' },
    person: { name: 'Giulia', initials: 'GB', photo: null },
    next_notice_on: null,
    href: '/documents/4',
    edit_href: null,
    card_href: '/documents/4',
    can_delete: false,
};

export default defineExamples(FmReminderRow, [
    { name: 'Late, postponed', props: { reminder: late } },
    { name: 'Written by hand', props: { reminder } },
    {
        name: 'Written by hand, about nothing',
        props: {
            reminder: {
                ...reminder,
                title: 'Rinnovare il passaporto di Laura',
                due_date: fromToday(10),
                area: 'general',
                subject: null,
            },
        },
    },
    {
        name: 'For everyone, planned expense missing',
        props: {
            reminder: {
                ...late,
                title: 'Rinnovare la polizza',
                due_date: fromToday(16),
                postponed_from: null,
                area: 'insurance',
                subject: { label: 'RC auto Panda', href: '/cards/2' },
                person: null,
                next_notice_on: fromToday(9),
                missing_planned_expense: true,
            },
        },
    },
    { name: 'Being marked done', props: { reminder, pending: true } },
    { name: 'Done', props: { reminder: { ...reminder, done: true } } },
]);
