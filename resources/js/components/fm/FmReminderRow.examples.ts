/**
 * The cases of `FmReminderRow` for the component catalogue: one written by
 * hand, an automatic one that is late, one with the planned expense missing
 * and one that is done.
 */
import { defineExamples } from '@/catalog/examples';
import { fromToday } from '@/catalog/dates';
import type { ReminderItem } from '@/lib/reminders';
import FmReminderRow from './FmReminderRow.vue';

/** A reminder written by hand, due in five days. */
const reminder: ReminderItem = {
    id: 1,
    title: 'Chiamare l’idraulico per il preventivo del bagno',
    due_date: fromToday(5),
    done: false,
    manual: true,
    area: 'home',
    subject: { label: 'Casa di via Roma', href: '/cards/home/1' },
    person: { name: 'Laura', initials: 'LB', photo: null },
    next_notice_on: fromToday(4),
    notices_sent: 0,
    missing_planned_expense: false,
    href: '/reminders/1/edit',
};

export default defineExamples(FmReminderRow, [
    { name: 'Written by hand', props: { reminder } },
    {
        name: 'Late',
        props: {
            reminder: {
                ...reminder,
                title: 'Rinnovare la tessera sanitaria',
                due_date: fromToday(-26),
                manual: false,
                area: 'health',
                subject: {
                    label: 'Tessera sanitaria di Giulia',
                    href: '/cards/1',
                },
                person: { name: 'Giulia', initials: 'GB', photo: null },
                next_notice_on: null,
                notices_sent: 3,
            },
        },
    },
    {
        name: 'Planned expense missing',
        props: {
            reminder: {
                ...reminder,
                title: 'Rinnovare la polizza',
                manual: false,
                area: 'insurance',
                subject: { label: 'RC auto Panda', href: '/cards/2' },
                notices_sent: 1,
                missing_planned_expense: true,
            },
        },
    },
    { name: 'Done', props: { reminder: { ...reminder, done: true } } },
]);
