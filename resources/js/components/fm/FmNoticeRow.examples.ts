/**
 * The cases of `FmNoticeRow` for the component catalogue: a new notice, one
 * seen whose reminder is late and one seen whose reminder is done.
 */
import { defineExamples } from '@/catalog/examples';
import type { NoticeItem } from '@/lib/reminders';
import FmNoticeRow from './FmNoticeRow.vue';

/** A notice not yet seen. */
const notice: NoticeItem = {
    id: 1,
    title: 'Rinnovare la polizza · RC auto Panda - UnipolSai',
    due_text: 'Scade il 21/11, tra 26 giorni',
    sent_at: '2026-10-07T06:00:00+02:00',
    read: false,
    area: 'insurance',
    state: null,
    href: '/cards/insurance/2',
};

export default defineExamples(FmNoticeRow, [
    { name: 'New', props: { notice } },
    {
        name: 'Seen, overdue',
        props: {
            notice: {
                ...notice,
                title: 'Rinnovare la tessera sanitaria di Giulia',
                due_text: 'Scadeva il 30/09: domani',
                read: true,
                area: 'health',
                state: 'overdue',
            },
        },
    },
    {
        name: 'Seen, done',
        props: {
            notice: { ...notice, read: true, area: 'paperwork', state: 'done' },
        },
    },
]);
